<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Command;

use Ketteler\Anmeldung\Oidc\Konfiguration;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
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

    private SymfonyStyle $io;

    private string $pat = '';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly SystemConfigService $config,
    ) {
        parent::__construct();
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
        foreach (self::TESTKUNDEN as $email => [$vorname, $nachname, $bestaetigt]) {
            $this->testkunde($orgId, $email, $vorname, $nachname, $bestaetigt, $testPasswort);
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

    private function testkunde(string $orgId, string $email, string $vorname, string $nachname, bool $bestaetigt, string $passwort): void
    {
        if ($passwort === '') {
            return;
        }
        $treffer = $this->api('POST', '/v2/users', ['queries' => [['emailQuery' => ['emailAddress' => $email, 'method' => 'TEXT_QUERY_METHOD_EQUALS']]]]);
        $id = $treffer['result'][0]['userId'] ?? null;
        if (\is_string($id)) {
            $this->api('POST', '/v2/users/' . $id . '/password', ['newPassword' => ['password' => $passwort, 'changeRequired' => false]]);

            return;
        }
        $this->api('POST', '/v2/users/human', [
            'organization' => ['orgId' => $orgId],
            'username' => $email,
            'profile' => ['givenName' => $vorname, 'familyName' => $nachname, 'preferredLanguage' => 'de'],
            'email' => $bestaetigt ? ['email' => $email, 'isVerified' => true] : ['email' => $email, 'returnCode' => (object) []],
            'password' => ['password' => $passwort, 'changeRequired' => false],
        ]);
        $this->io->writeln('Testkunde ' . $email . ' angelegt' . ($bestaetigt ? '.' : ' (E-Mail unbestätigt).'));
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
