<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Command;

use Ketteler\Anmeldung\Konto\KundenSpeicher;
use Ketteler\Anmeldung\Oidc\Konfiguration;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Nur für die lokale Entwicklung: richtet den ZITADEL-Container ein (idempotent) und trägt Client-ID und
 * Organisation in die Plugin-Konfiguration ein. Braucht den Zugangsschlüssel des Einrichtungskontos, den ZITADEL beim
 * ersten Start in das Volume schreibt (KETTELER_ZITADEL_PAT_DATEI). Auf Staging und Live richtet man ZITADEL anders ein
 * (siehe docs/plan.md), dort gibt es diese Datei nicht.
 */
#[AsCommand(name: 'ketteler:zitadel:einrichten', description: 'Lokalen ZITADEL einrichten: Kunden-Organisation, Anwendung Shop, Testkunden')]
class ZitadelEinrichtenCommand extends Command
{
    public const ORGANISATION = 'KETTELER Kunden';
    public const PROJEKT = 'Ketteler Shop';
    public const ANWENDUNG = 'Shop';

    /** Lokale Testkunden: E-Mail => [Vorname, Nachname, E-Mail bestätigt] */
    public const TESTKUNDEN = [
        'testkunde@ketteler.test' => ['Tessa', 'Testkundin', true],
        'testkunde2@ketteler.test' => ['Theo', 'Testkunde', true],
        'unbestaetigt@ketteler.test' => ['Uwe', 'Unbestätigt', false],
    ];

    /**
     * Testkonten für die Abnahme (Gesamtauftrag D): ZITADEL-Konto und fertiges Kundenkonto im Shop mit Anschrift, damit
     * nach der Anmeldung sofort bestellt werden kann. E-Mail => [Vorname, Nachname, Firma oder null, USt-IdNr. oder null,
     * Straße, PLZ, Ort, Land]. Die USt-IdNr. sind öffentlich (Impressum KETTELER-Verlag bzw. OMV, siehe .env-Notiz zum
     * VIES-Test); der Shop prüft sie beim Speichern bei VIES. Passwort: KETTELER_TESTKUNDE_PASSWORT (nur .env).
     */
    public const TESTKONTEN = [
        'privat-de@ketteler.test' => ['Paula', 'Privat', null, null, 'Musterstraße 1', '93449', 'Waldmünchen', 'DE'],
        'privat-at@ketteler.test' => ['Anton', 'Alpen', null, null, 'Testgasse 2', '1010', 'Wien', 'AT'],
        'firma-de@ketteler.test' => ['Franz', 'Firma', 'Testfirma GmbH (Test)', 'DE122792915', 'Firmenweg 3', '50670', 'Köln', 'DE'],
        'einrichtung-at@ketteler.test' => ['Eva', 'Einrichtung', 'Testeinrichtung Wien (Test)', 'ATU14189108', 'Einrichtungsplatz 4', '1020', 'Wien', 'AT'],
    ];

    private SymfonyStyle $io;

    private string $pat = '';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly SystemConfigService $config,
        private readonly ?DefinitionInstanceRegistry $registry = null,
        private readonly ?KundenSpeicher $kundenSpeicher = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('admin-passwort', null, InputOption::VALUE_NONE, 'Passwort des ZITADEL-Admins (zitadel-admin@ketteler.test) auf ZITADEL_ADMIN_PASSWORT aus der .env setzen');
        $this->addOption('testkonten', null, InputOption::VALUE_NONE, 'Testkonten Privat DE, Privat AT, Firma DE, Einrichtung AT in ZITADEL und im Shop anlegen (Passwort: KETTELER_TESTKUNDE_PASSWORT)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $datei = Konfiguration::env('KETTELER_ZITADEL_PAT_DATEI');
        if ($datei === '' || !is_readable($datei)) {
            $this->io->warning('Kein Zugangsschlüssel für ZITADEL gefunden (KETTELER_ZITADEL_PAT_DATEI). Nur lokal nötig, übersprungen.');

            return self::SUCCESS;
        }
        $this->pat = trim((string) file_get_contents($datei));
        $shop = rtrim(Konfiguration::env('APP_URL'), '/');
        $testPasswort = Konfiguration::env('KETTELER_TESTKUNDE_PASSWORT');

        $orgId = $this->organisation();
        $this->loginRichtlinie($orgId);
        $projektId = $this->projekt();
        $clientId = $this->anwendung($projektId, $shop);
        $this->mailversand();
        $this->gestaltung($orgId);
        foreach (self::TESTKUNDEN as $email => [$vorname, $nachname, $bestaetigt]) {
            $this->testkunde($orgId, $email, $vorname, $nachname, $bestaetigt, $testPasswort);
        }
        if ($input->getOption('testkonten')) {
            foreach (self::TESTKONTEN as $email => $konto) {
                $sub = $this->testkunde($orgId, $email, $konto[0], $konto[1], true, $testPasswort);
                if ($sub !== null) {
                    $this->shopkonto($email, $sub, $konto);
                }
            }
        }
        if ($input->getOption('admin-passwort')) {
            // Erneuern (z. B. nachdem ein Wert offen lag): neuer Wert nur in der .env, hier ohne Ausgabe übernommen
            $neu = Konfiguration::env('ZITADEL_ADMIN_PASSWORT');
            $treffer = $this->api('POST', '/v2/users', ['queries' => [['emailQuery' => ['emailAddress' => 'zitadel-admin@ketteler.test', 'method' => 'TEXT_QUERY_METHOD_EQUALS']]]]);
            $id = $treffer['result'][0]['userId'] ?? null;
            if ($neu === '' || !\is_string($id)) {
                throw new \RuntimeException('ZITADEL-Admin nicht gefunden oder ZITADEL_ADMIN_PASSWORT leer.');
            }
            $this->api('POST', '/v2/users/' . $id . '/password', ['newPassword' => ['password' => $neu, 'changeRequired' => false]]);
            $this->io->writeln('Passwort des ZITADEL-Admins aus der .env übernommen.');
        }

        $this->config->set('KettelerAnmeldung.config.clientId', $clientId);
        $this->config->set('KettelerAnmeldung.config.organisationId', $orgId);
        $this->io->success('ZITADEL eingerichtet. Client-ID und Organisation stehen in der Plugin-Konfiguration.');

        return self::SUCCESS;
    }

    private function organisation(): string
    {
        $treffer = $this->api('POST', '/v2/organizations/_search', ['queries' => [['nameQuery' => ['name' => self::ORGANISATION, 'method' => 'TEXT_QUERY_METHOD_EQUALS']]]]);
        $id = $treffer['result'][0]['id'] ?? null;
        if (!\is_string($id)) {
            $id = $this->api('POST', '/v2/organizations', ['name' => self::ORGANISATION])['organizationId'];
            $this->io->writeln('Organisation „' . self::ORGANISATION . '“ angelegt.');
        }

        return $id;
    }

    /** Selbstregistrierung mit Benutzername und Passwort in der Kunden-Organisation erlauben. */
    private function loginRichtlinie(string $orgId): void
    {
        $richtlinie = [
            'allowUsernamePassword' => true,
            'allowRegister' => true,
            'allowExternalIdp' => false,
            'forceMfa' => false,
            'passwordlessType' => 'PASSWORDLESS_TYPE_NOT_ALLOWED',
            'hidePasswordReset' => false,
            'ignoreUnknownUsernames' => true,
            'defaultRedirectUri' => '',
            // Gültigkeiten wie in der ZITADEL-Vorgabe; fehlen sie, ist jede Passwortprüfung sofort abgelaufen
            'passwordCheckLifetime' => '864000s',
            'externalLoginCheckLifetime' => '864000s',
            'secondFactorCheckLifetime' => '64800s',
            'multiFactorCheckLifetime' => '43200s',
            // 0 = kein Hinweis zur Einrichtung eines zweiten Faktors nach der Anmeldung
            'mfaInitSkipLifetime' => '0s',
        ];
        // Anlegen; gibt es schon eine eigene Richtlinie (409), wird sie aktualisiert (400 = keine Änderung)
        if ($this->api('POST', '/management/v1/policies/login', $richtlinie, $orgId, [409]) === []) {
            $this->api('PUT', '/management/v1/policies/login', $richtlinie, $orgId, [400]);
        }
    }

    private function projekt(): string
    {
        $treffer = $this->api('POST', '/management/v1/projects/_search', ['queries' => [['nameQuery' => ['name' => self::PROJEKT, 'method' => 'TEXT_QUERY_METHOD_EQUALS']]]]);
        $id = $treffer['result'][0]['id'] ?? null;
        if (!\is_string($id)) {
            $id = $this->api('POST', '/management/v1/projects', ['name' => self::PROJEKT])['id'];
            $this->io->writeln('Projekt „' . self::PROJEKT . '“ angelegt.');
        }

        return $id;
    }

    /** Anwendung „Shop“: Web, Authorization Code mit PKCE, ohne Client-Geheimnis. */
    private function anwendung(string $projektId, string $shop): string
    {
        $einstellungen = [
            'redirectUris' => [$shop . '/account/oidc/rueckkehr'],
            'postLogoutRedirectUris' => [$shop . '/'],
            'responseTypes' => ['OIDC_RESPONSE_TYPE_CODE'],
            'grantTypes' => ['OIDC_GRANT_TYPE_AUTHORIZATION_CODE'],
            'appType' => 'OIDC_APP_TYPE_WEB',
            'authMethodType' => 'OIDC_AUTH_METHOD_TYPE_NONE',
            'version' => 'OIDC_VERSION_1_0',
            'devMode' => str_starts_with($shop, 'http://'),
            'accessTokenType' => 'OIDC_TOKEN_TYPE_BEARER',
            'idTokenUserinfoAssertion' => true,
            'idTokenRoleAssertion' => false,
            'accessTokenRoleAssertion' => false,
        ];
        $treffer = $this->api('POST', '/management/v1/projects/' . $projektId . '/apps/_search', ['queries' => [['nameQuery' => ['name' => self::ANWENDUNG, 'method' => 'TEXT_QUERY_METHOD_EQUALS']]]]);
        $app = $treffer['result'][0] ?? null;
        if (\is_array($app)) {
            // 400 = keine Änderung
            $this->api('PUT', '/management/v1/projects/' . $projektId . '/apps/' . $app['id'] . '/oidc_config', $einstellungen, null, [400]);

            return (string) $app['oidcConfig']['clientId'];
        }
        $neu = $this->api('POST', '/management/v1/projects/' . $projektId . '/apps/oidc', ['name' => self::ANWENDUNG] + $einstellungen);
        $this->io->writeln('Anwendung „' . self::ANWENDUNG . '“ angelegt.');

        return (string) $neu['clientId'];
    }

    /** Mails von ZITADEL (Registrierung, Passwort vergessen) an Mailpit. */
    private function mailversand(): void
    {
        $vorhanden = $this->api('POST', '/admin/v1/smtp/_search', []);
        foreach ($vorhanden['result'] ?? [] as $eintrag) {
            if (($eintrag['host'] ?? '') === 'mailer:1025') {
                if (($eintrag['state'] ?? '') !== 'SMTP_CONFIG_ACTIVE') {
                    $this->api('POST', '/admin/v1/smtp/' . $eintrag['id'] . '/_activate', []);
                }

                return;
            }
        }
        $neu = $this->api('POST', '/admin/v1/smtp', [
            'senderAddress' => 'anmeldung@ketteler.test',
            'senderName' => 'KETTELER-Verlag (lokal)',
            'host' => 'mailer:1025',
            'tls' => false,
            'user' => '',
            'password' => '',
            'description' => 'Mailpit lokal',
        ]);
        $this->api('POST', '/admin/v1/smtp/' . $neu['id'] . '/_activate', []);
        $this->io->writeln('Mailversand von ZITADEL an Mailpit eingerichtet.');
    }

    /** @return string|null ZITADEL-Benutzer-ID (sub) */
    /**
     * Mails und Anmeldeseiten von ZITADEL im Stil des Verlags: Farben, Logo (falls lesbar), deutsche Texte in Sie-Form
     * ohne Gedankenstriche für Bestätigung der E-Mail-Adresse, Passwort vergessen, Passwort geändert, Konto einrichten.
     */
    private function gestaltung(string $orgId): void
    {
        $farben = [
            'primaryColor' => '#b7005c', 'backgroundColor' => '#fbf8f3', 'warnColor' => '#b7005c', 'fontColor' => '#2a2326',
            'primaryColorDark' => '#f7d4e4', 'backgroundColorDark' => '#2a2326', 'warnColorDark' => '#f7d4e4', 'fontColorDark' => '#fbf8f3',
            'hideLoginNameSuffix' => true, 'disableWatermark' => true,
        ];
        if ($this->api('POST', '/management/v1/policies/label', $farben, $orgId, [409]) === []) {
            $this->api('PUT', '/management/v1/policies/label', $farben, $orgId, [400]);
        }
        $logo = \dirname(__DIR__, 3) . '/KettelerTheme/src/Resources/public/logo/ketteler-logo-fachverlag.png';
        if (is_readable($logo)) {
            $this->datei('/assets/v1/org/policy/label/logo', $logo, $orgId);
        }
        $this->api('POST', '/management/v1/policies/label/_activate', [], $orgId, [400]);

        $fuss = 'KETTELER-Verlag GmbH · Kundenservice 09972 94 14-51 · kontakt@ketteler-verlag.de';
        $texte = [
            'verifyemail' => ['Bitte bestätigen Sie Ihre E-Mail-Adresse', 'E-Mail-Adresse bestätigen',
                'bitte bestätigen Sie Ihre E-Mail-Adresse für Ihr Kundenkonto beim KETTELER-Verlag. Ihr Code lautet {{.Code}}.', 'E-Mail-Adresse bestätigen'],
            'passwordreset' => ['Neues Passwort für Ihr Kundenkonto', 'Passwort zurücksetzen',
                'Sie haben ein neues Passwort für Ihr Kundenkonto beim KETTELER-Verlag angefordert. Ihr Code lautet {{.Code}}. Haben Sie das nicht veranlasst, können Sie diese E-Mail ignorieren.', 'Neues Passwort festlegen'],
            'password_change' => ['Ihr Passwort wurde geändert', 'Passwort geändert',
                'das Passwort für Ihr Kundenkonto beim KETTELER-Verlag wurde geändert. Waren Sie das nicht, melden Sie sich bitte bei unserem Kundenservice.', 'Zur Anmeldung'],
            'init' => ['Ihr Kundenkonto beim KETTELER-Verlag', 'Kundenkonto einrichten',
                'für Sie wurde ein Kundenkonto beim KETTELER-Verlag angelegt. Bitte legen Sie Ihr Passwort fest. Ihr Code lautet {{.Code}}.', 'Passwort festlegen'],
        ];
        foreach ($texte as $art => [$betreff, $titel, $text, $knopf]) {
            $this->api('PUT', '/management/v1/text/message/' . $art . '/de', [
                'title' => $titel, 'preHeader' => $titel, 'subject' => $betreff, 'greeting' => 'Guten Tag {{.DisplayName}},',
                'text' => $text, 'buttonText' => $knopf, 'footerText' => $fuss,
            ], $orgId, [400]);
        }
        $this->io->writeln('ZITADEL: Farben, Logo und deutsche Mailtexte (Sie-Form) gesetzt.');
    }

    /** Datei hochladen (Assets-API von ZITADEL, multipart) */
    private function datei(string $pfad, string $datei, string $orgId): void
    {
        $oeffentlich = parse_url(rtrim(Konfiguration::env('KETTELER_ZITADEL_URL'), '/'));
        $basis = rtrim(Konfiguration::env('KETTELER_ZITADEL_INTERN') ?: Konfiguration::env('KETTELER_ZITADEL_URL'), '/');
        $formular = new FormDataPart(['file' => DataPart::fromPath($datei, 'logo.png', 'image/png')]);
        $kopf = $formular->getPreparedHeaders()->toArray();
        $kopf[] = 'Authorization: Bearer ' . $this->pat;
        $kopf[] = 'Host: ' . ($oeffentlich['host'] ?? 'localhost') . (isset($oeffentlich['port']) ? ':' . $oeffentlich['port'] : '');
        $kopf[] = 'x-zitadel-orgid: ' . $orgId;
        $antwort = $this->http->request('POST', $basis . $pfad, ['headers' => $kopf, 'body' => $formular->bodyToIterable(), 'timeout' => 20]);
        if ($antwort->getStatusCode() >= 400) {
            $this->io->warning('ZITADEL: Logo nicht hochgeladen (' . $antwort->getStatusCode() . ').');
        }
    }

    private function testkunde(string $orgId, string $email, string $vorname, string $nachname, bool $bestaetigt, string $passwort): ?string
    {
        if ($passwort === '') {
            return null;
        }
        $treffer = $this->api('POST', '/v2/users', ['queries' => [['emailQuery' => ['emailAddress' => $email, 'method' => 'TEXT_QUERY_METHOD_EQUALS']]]]);
        $id = $treffer['result'][0]['userId'] ?? null;
        if (\is_string($id)) {
            $this->api('POST', '/v2/users/' . $id . '/password', ['newPassword' => ['password' => $passwort, 'changeRequired' => false]]);

            return $id;
        }
        $neu = $this->api('POST', '/v2/users/human', [
            'organization' => ['orgId' => $orgId],
            'username' => $email,
            'profile' => ['givenName' => $vorname, 'familyName' => $nachname, 'preferredLanguage' => 'de'],
            'email' => $bestaetigt ? ['email' => $email, 'isVerified' => true] : ['email' => $email, 'returnCode' => (object) []],
            'password' => ['password' => $passwort, 'changeRequired' => false],
        ]);
        $this->io->writeln('Testkunde ' . $email . ' angelegt' . ($bestaetigt ? '.' : ' (E-Mail unbestätigt).'));

        return \is_string($neu['userId'] ?? null) ? $neu['userId'] : null;
    }

    /**
     * Kundenkonto im Shop zum ZITADEL-Konto: anlegen, wenn es die E-Mail noch nicht gibt, sonst Anschrift und USt-IdNr.
     * auf den Stand der Liste bringen; danach mit dem ZITADEL-Konto verknüpfen (wie bei der ersten Anmeldung).
     *
     * @param array{0: string, 1: string, 2: ?string, 3: ?string, 4: string, 5: string, 6: string, 7: string} $konto
     */
    private function shopkonto(string $email, string $sub, array $konto): void
    {
        if ($this->registry === null || $this->kundenSpeicher === null) {
            return;
        }
        $context = Context::createCLIContext();
        [$vorname, $nachname, $firma, $ustid, $strasse, $plz, $ort, $iso] = $konto;
        // Österreichische Test-UID aus der .env (Notiz zur Quelle dort), sonst die öffentliche aus der Liste
        if ($iso === 'AT' && $ustid !== null && Konfiguration::env('KETTELER_TEST_USTID_AT') !== '') {
            $ustid = Konfiguration::env('KETTELER_TEST_USTID_AT');
        }
        $kanal = $this->registry->getRepository('sales_channel')->search(
            (new Criteria())->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))->setLimit(1),
            $context,
        )->first();
        $erste = fn (string $entity, string $feld, string $wert): ?string => $this->registry->getRepository($entity)
            ->searchIds((new Criteria())->addFilter(new EqualsFilter($feld, $wert))->setLimit(1), $context)->firstId();
        $id = Uuid::fromStringToHex('ketteler.testkonto.' . $email);
        $adresse = Uuid::fromStringToHex('ketteler.testkonto.' . $email . '.adresse');
        $vorhanden = $erste('customer', 'email', $email);
        if ($vorhanden !== null && $vorhanden !== $id) {
            $id = $vorhanden;
            $adresse = $this->registry->getRepository('customer')->search(new Criteria([$id]), $context)->first()?->getDefaultBillingAddressId() ?? $adresse;
        }
        $anschrift = ['id' => $adresse, 'customerId' => $id, 'firstName' => $vorname, 'lastName' => $nachname, 'salutationId' => $erste('salutation', 'salutationKey', 'not_specified'),
            'street' => $strasse, 'zipcode' => $plz, 'city' => $ort, 'countryId' => $erste('country', 'iso', $iso), 'company' => $firma];
        $daten = [
            'id' => $id, 'email' => $email, 'firstName' => $vorname, 'lastName' => $nachname, 'salutationId' => $anschrift['salutationId'],
            'accountType' => $firma !== null ? 'business' : 'private', 'company' => $firma, 'vatIds' => $ustid !== null ? [$ustid] : null,
            'defaultBillingAddressId' => $adresse, 'defaultShippingAddressId' => $adresse, 'addresses' => [$anschrift],
        ];
        if ($vorhanden === null) {
            $daten += [
                'customerNumber' => 'TK-' . strtoupper(substr($id, 0, 6)), 'salesChannelId' => $kanal?->getId(), 'groupId' => $kanal?->getCustomerGroupId(),
                'defaultPaymentMethodId' => $kanal?->getPaymentMethodId(), 'password' => bin2hex(random_bytes(24)), 'active' => true,
            ];
        }
        $this->registry->getRepository('customer')->upsert([$daten], $context);
        $this->kundenSpeicher->subSetzen($id, $sub, $context);
        $this->io->writeln('Testkonto ' . $email . ($vorhanden === null ? ' im Shop angelegt.' : ' im Shop aktualisiert.'));
    }

    /**
     * @param array<string, mixed>|null $koerper
     * @param list<int> $erlaubt Fehlercodes, die hingenommen werden
     *
     * @return array<string, mixed>
     */
    private function api(string $methode, string $pfad, ?array $koerper = null, ?string $orgId = null, array $erlaubt = []): array
    {
        $oeffentlich = parse_url(rtrim(Konfiguration::env('KETTELER_ZITADEL_URL'), '/'));
        $basis = rtrim(Konfiguration::env('KETTELER_ZITADEL_INTERN') ?: Konfiguration::env('KETTELER_ZITADEL_URL'), '/');
        $kopf = [
            'Authorization' => 'Bearer ' . $this->pat,
            'Host' => ($oeffentlich['host'] ?? 'localhost') . (isset($oeffentlich['port']) ? ':' . $oeffentlich['port'] : ''),
            'Accept' => 'application/json',
        ];
        if ($orgId !== null) {
            $kopf['x-zitadel-orgid'] = $orgId;
        }
        $optionen = ['headers' => $kopf, 'timeout' => 20];
        if ($koerper !== null) {
            $optionen['json'] = $koerper === [] ? new \stdClass() : $koerper;
        }
        $antwort = $this->http->request($methode, $basis . $pfad, $optionen);
        $status = $antwort->getStatusCode();
        $inhalt = $antwort->getContent(false);
        if ($status >= 400) {
            if (\in_array($status, $erlaubt, true)) {
                return [];
            }
            throw new \RuntimeException(\sprintf('ZITADEL %s %s: %d %s', $methode, $pfad, $status, mb_substr($inhalt, 0, 400)));
        }

        return $inhalt === '' ? [] : (array) json_decode($inhalt, true);
    }
}
