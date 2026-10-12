<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Storefront;

use Ketteler\Anmeldung\Konto\KontoAnlage;
use Ketteler\Anmeldung\Konto\Verknuepfung;
use Ketteler\Anmeldung\Konto\Zuordnung;
use Ketteler\Anmeldung\Oidc\Identitaet;
use Ketteler\Anmeldung\Oidc\IdTokenPruefer;
use Ketteler\Anmeldung\Oidc\Konfiguration;
use Ketteler\Anmeldung\Oidc\OidcFehler;
use Ketteler\Anmeldung\Oidc\Pkce;
use Ketteler\Anmeldung\Oidc\ZitadelClient;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Anmeldung über ZITADEL (Authorization Code mit PKCE):
 * /account/oidc/anmelden → ZITADEL → /account/oidc/rueckkehr → Kunde zuordnen und anmelden
 * (oder beim ersten Mal /account/oidc/vervollstaendigen für die Rechnungsanschrift).
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class AnmeldungController extends StorefrontController
{
    public const SITZUNG_VORGANG = 'ketteler_oidc_vorgang';
    public const SITZUNG_NEU = 'ketteler_oidc_neu';
    public const SITZUNG_ID_TOKEN = 'ketteler_oidc_id_token';
    private const VORGANG_GUELTIG = 900;

    public function __construct(
        private readonly Konfiguration $konfiguration,
        private readonly ZitadelClient $zitadel,
        private readonly IdTokenPruefer $pruefer,
        private readonly Verknuepfung $verknuepfung,
        private readonly KontoAnlage $kontoAnlage,
        private readonly AccountService $accountService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/account/oidc/anmelden', name: 'frontend.ketteler.anmelden', defaults: ['_noStore' => true], methods: ['GET'])]
    public function anmelden(Request $request, SalesChannelContext $context): Response
    {
        $kanal = $context->getSalesChannelId();
        if (!$this->konfiguration->eingerichtet($kanal)) {
            $this->addFlash(self::DANGER, $this->trans('ketteler.anmeldung.nichtEingerichtet'));

            return $this->redirectToRoute('frontend.home.page');
        }

        $state = Pkce::zufall();
        $nonce = Pkce::zufall();
        $verifier = Pkce::verifier();
        $request->getSession()->set(self::SITZUNG_VORGANG, [
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'ziel' => self::sicheresZiel($request->query->get('ziel')),
            'zeit' => time(),
        ]);

        $scopes = ['openid', 'profile', 'email'];
        $organisation = $this->konfiguration->organisationId($kanal);
        if ($organisation !== '') {
            // Nur Konten der Kunden-Organisation; Registrierung landet ebenfalls dort
            $scopes[] = 'urn:zitadel:iam:org:id:' . $organisation;
        }
        $parameter = [
            'client_id' => $this->konfiguration->clientId($kanal),
            'redirect_uri' => $this->rueckkehrAdresse(),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => Pkce::challenge($verifier),
            'code_challenge_method' => 'S256',
            'ui_locales' => 'de',
        ];
        if ($request->query->getBoolean('registrieren')) {
            $parameter['prompt'] = 'create';
        }

        try {
            return $this->redirect($this->zitadel->anmeldeAdresse($parameter));
        } catch (OidcFehler $e) {
            return $this->fehler($e, 'ketteler.anmeldung.nichtErreichbar');
        }
    }

    #[Route(path: '/account/oidc/rueckkehr', name: 'frontend.ketteler.rueckkehr', defaults: ['_noStore' => true], methods: ['GET'])]
    public function rueckkehr(Request $request, SalesChannelContext $context): Response
    {
        $sitzung = $request->getSession();
        $vorgang = $sitzung->get(self::SITZUNG_VORGANG);
        $sitzung->remove(self::SITZUNG_VORGANG);

        if ($request->query->has('error')) {
            // z. B. Abbruch auf der Anmeldeseite
            $this->logger->info('Ketteler-Anmeldung: ZITADEL meldet ' . $request->query->get('error'));
            $this->addFlash(self::INFO, $this->trans('ketteler.anmeldung.abgebrochen'));

            return $this->redirectToRoute('frontend.account.login.page');
        }
        $state = (string) $request->query->get('state', '');
        if (!\is_array($vorgang) || $state === '' || !hash_equals((string) $vorgang['state'], $state)
            || time() - (int) $vorgang['zeit'] > self::VORGANG_GUELTIG) {
            return $this->fehler(new OidcFehler('state fehlt, passt nicht oder ist abgelaufen.'), 'ketteler.anmeldung.abgelaufen');
        }

        try {
            $clientId = $this->konfiguration->clientId($context->getSalesChannelId());
            $tokens = $this->zitadel->codeEinloesen((string) $request->query->get('code', ''), $vorgang['verifier'], $this->rueckkehrAdresse(), $clientId);
            $identitaet = $this->pruefer->pruefe($tokens['id_token'], $this->zitadel->schluessel(), $this->konfiguration->aussteller(), $clientId, $vorgang['nonce']);
        } catch (OidcFehler $e) {
            return $this->fehler($e, 'ketteler.anmeldung.fehlgeschlagen');
        }

        $zuordnung = $this->verknuepfung->ordneZu($identitaet, $context->getContext());
        if ($zuordnung->art === Zuordnung::EMAIL_UNBESTAETIGT) {
            $this->addFlash(self::DANGER, $this->trans('ketteler.anmeldung.emailUnbestaetigt'));

            return $this->redirectToRoute('frontend.account.login.page');
        }
        if ($zuordnung->art === Zuordnung::KONFLIKT) {
            $this->logger->warning('Ketteler-Anmeldung: E-Mail gehört zu einem Kundenkonto mit anderer ZITADEL-Kennung.', ['sub' => $identitaet->sub]);
            $this->addFlash(self::DANGER, $this->trans('ketteler.anmeldung.konflikt'));

            return $this->redirectToRoute('frontend.account.login.page');
        }
        if ($zuordnung->art === Zuordnung::NEU) {
            $sitzung->set(self::SITZUNG_NEU, ['identitaet' => self::identitaetAlsArray($identitaet), 'ziel' => $vorgang['ziel'], 'zeit' => time()]);

            return $this->redirectToRoute('frontend.ketteler.vervollstaendigen');
        }

        $this->accountService->loginById((string) $zuordnung->kundeId, $context);
        $request->getSession()->set(self::SITZUNG_ID_TOKEN, $identitaet->idToken);

        return $this->zumZiel($vorgang['ziel']);
    }

    #[Route(path: '/account/oidc/vervollstaendigen', name: 'frontend.ketteler.vervollstaendigen', defaults: ['_noStore' => true], methods: ['GET', 'POST'])]
    public function vervollstaendigen(Request $request, SalesChannelContext $context): Response
    {
        $neu = $request->getSession()->get(self::SITZUNG_NEU);
        if (!\is_array($neu) || time() - (int) $neu['zeit'] > self::VORGANG_GUELTIG) {
            $request->getSession()->remove(self::SITZUNG_NEU);
            $this->addFlash(self::INFO, $this->trans('ketteler.anmeldung.abgelaufen'));

            return $this->redirectToRoute('frontend.account.login.page');
        }
        $identitaet = self::identitaetAusArray($neu['identitaet']);
        $daten = $request->request->all();
        $fehler = null;

        if ($request->isMethod('POST')) {
            try {
                $kundeId = $this->kontoAnlage->anlegen($identitaet, $daten, $context);
                $request->getSession()->remove(self::SITZUNG_NEU);
                $request->getSession()->set(self::SITZUNG_ID_TOKEN, $identitaet->idToken);
                $this->logger->info('Ketteler-Anmeldung: Kundenkonto angelegt.', ['kunde' => $kundeId]);
                $this->addFlash(self::SUCCESS, $this->trans('ketteler.anmeldung.kontoAngelegt'));

                return $this->zumZiel($neu['ziel']);
            } catch (ConstraintViolationException $e) {
                $fehler = $e;
            }
        }

        return $this->renderStorefront('@KettelerAnmeldung/storefront/page/ketteler/vervollstaendigen.html.twig', [
            'identitaet' => $identitaet,
            'daten' => $daten,
            'formViolations' => $fehler,
        ]);
    }

    /** Nur Pfade im eigenen Shop, nie fremde Adressen (offene Weiterleitung). */
    public static function sicheresZiel(mixed $ziel): ?string
    {
        if (!\is_string($ziel) || $ziel === '' || $ziel[0] !== '/' || str_starts_with($ziel, '//') || str_contains($ziel, '\\')
            || preg_match('/[\x00-\x1f]/', $ziel)) {
            return null;
        }

        return $ziel;
    }

    private function zumZiel(?string $ziel): Response
    {
        return $ziel !== null ? $this->redirect($ziel) : $this->redirectToRoute('frontend.account.home.page');
    }

    private function rueckkehrAdresse(): string
    {
        return $this->generateUrl('frontend.ketteler.rueckkehr', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function fehler(OidcFehler $e, string $text): Response
    {
        $this->logger->error('Ketteler-Anmeldung: ' . $e->getMessage());
        $this->addFlash(self::DANGER, $this->trans($text));

        return $this->redirectToRoute('frontend.account.login.page');
    }

    /** @return array<string, mixed> */
    private static function identitaetAlsArray(Identitaet $i): array
    {
        return ['sub' => $i->sub, 'email' => $i->email, 'emailBestaetigt' => $i->emailBestaetigt, 'vorname' => $i->vorname, 'nachname' => $i->nachname, 'idToken' => $i->idToken];
    }

    /** @param array<string, mixed> $a */
    private static function identitaetAusArray(array $a): Identitaet
    {
        return new Identitaet((string) $a['sub'], (string) $a['email'], (bool) $a['emailBestaetigt'], (string) $a['vorname'], (string) $a['nachname'], (string) $a['idToken']);
    }
}
