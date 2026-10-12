<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Storefront;

use Ketteler\Anmeldung\Oidc\OidcFehler;
use Ketteler\Anmeldung\Oidc\ZitadelClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Kunden melden sich nur über ZITADEL an. Die Passwortwege von Shopware leiten deshalb zur ZITADEL-Anmeldung;
 * die Gastbestellung (nur Print) bleibt. Beim Abmelden wird zusätzlich die ZITADEL-Sitzung beendet.
 */
class PasswortWegeSubscriber implements EventSubscriberInterface
{
    /** Shopware-Route => [Ziel-Route, Parameter] */
    private const UMLEITUNGEN = [
        'frontend.account.login' => ['frontend.ketteler.anmelden', []],
        'frontend.account.register.page' => ['frontend.ketteler.anmelden', ['registrieren' => 1]],
        'frontend.account.recover.page' => ['frontend.ketteler.anmelden', []],
        'frontend.account.recover.request' => ['frontend.ketteler.anmelden', []],
        'frontend.account.recover.password.page' => ['frontend.ketteler.anmelden', []],
        'frontend.account.recover.password.reset' => ['frontend.ketteler.anmelden', []],
        'frontend.account.profile.email.save' => ['frontend.account.profile.page', []],
        'frontend.account.profile.password.save' => ['frontend.account.profile.page', []],
    ];

    private const ABMELDEN_TOKEN = '_ketteler_id_token';

    public function __construct(
        private readonly UrlGeneratorInterface $router,
        private readonly ZitadelClient $zitadel,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // nach dem Routing (RouterListener hat Priorität 32), vor dem Controller
            KernelEvents::REQUEST => ['vorDemController', 10],
            KernelEvents::RESPONSE => ['nachDemAbmelden', 0],
        ];
    }

    public function vorDemController(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');

        if ($route === 'frontend.account.register.save') {
            // Gastbestellung (ohne „Kundenkonto anlegen“) bleibt erlaubt; Konten entstehen nur über ZITADEL
            if (!$request->request->getBoolean('createCustomerAccount')) {
                return;
            }
            $event->setResponse(new RedirectResponse($this->router->generate('frontend.ketteler.anmelden', ['registrieren' => 1])));

            return;
        }
        if ($route === 'frontend.account.logout.page' && $request->hasSession()) {
            $request->attributes->set(self::ABMELDEN_TOKEN, $request->getSession()->get(AnmeldungController::SITZUNG_ID_TOKEN));

            return;
        }
        if (isset(self::UMLEITUNGEN[$route])) {
            [$ziel, $parameter] = self::UMLEITUNGEN[$route];
            $event->setResponse(new RedirectResponse($this->router->generate($ziel, $parameter)));
        }
    }

    public function nachDemAbmelden(ResponseEvent $event): void
    {
        $idToken = $event->getRequest()->attributes->get(self::ABMELDEN_TOKEN);
        if (!\is_string($idToken) || $idToken === '' || !$event->getResponse()->isRedirection()) {
            return;
        }
        try {
            $zurueck = $this->router->generate('frontend.home.page', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $event->setResponse(new RedirectResponse($this->zitadel->abmeldeAdresse($idToken, $zurueck)));
        } catch (OidcFehler $e) {
            // Shop-Abmeldung ist erfolgt; die ZITADEL-Sitzung läuft dann von selbst ab
            $this->logger->warning('Ketteler-Anmeldung: Abmelden bei ZITADEL nicht möglich: ' . $e->getMessage());
        }
    }
}
