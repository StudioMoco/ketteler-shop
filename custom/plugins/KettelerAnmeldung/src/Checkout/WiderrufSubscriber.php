<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Checkout;

use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Bestellen im Shop: Fehlt bei Privatkunden mit Online-Artikel das Häkchen, zurück zur Kasse mit Hinweis.
 * Ist es gesetzt, steht die Zustimmung mit Zeitpunkt und Wortlaut an der Bestellung (Zusatzfeld ketteler_widerruf).
 * Bestellungen aus der Shopware-Verwaltung sind ausgenommen.
 */
class WiderrufSubscriber implements EventSubscriberInterface
{
    private const ROUTE = 'frontend.checkout.finish.order';

    public function __construct(
        private readonly CartService $warenkorb,
        private readonly RequestStack $anfragen,
        private readonly UrlGeneratorInterface $router,
        private readonly TranslatorInterface $uebersetzer,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // nach dem Auflösen des Kontexts (Shopware: KernelEvents::CONTROLLER, Priorität -10)
            KernelEvents::CONTROLLER => ['pruefen', -20],
            CartConvertedEvent::class => 'merken',
        ];
    }

    public function pruefen(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->get('_route') !== self::ROUTE || $request->request->getBoolean(Widerruf::FELD)) {
            return;
        }
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof SalesChannelContext || !Widerruf::noetig($this->warenkorb->getCart($context->getToken(), $context), $context)) {
            return;
        }
        $sitzung = $request->hasSession() ? $request->getSession() : null;
        if ($sitzung instanceof FlashBagAwareSessionInterface) {
            $sitzung->getFlashBag()->add('danger', $this->uebersetzer->trans('ketteler.widerruf.fehlt'));
        }
        $ziel = $this->router->generate('frontend.checkout.confirm.page');
        $event->setController(static fn () => new RedirectResponse($ziel));
    }

    public function merken(CartConvertedEvent $event): void
    {
        $request = $this->anfragen->getCurrentRequest();
        if ($request === null || $request->attributes->get('_route') !== self::ROUTE || !$request->request->getBoolean(Widerruf::FELD)) {
            return;
        }
        if (!Widerruf::noetig($event->getCart(), $event->getSalesChannelContext())) {
            return;
        }
        $bestellung = $event->getConvertedCart();
        $bestellung['customFields'][Widerruf::FELD] = [
            'zugestimmt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'text' => $this->uebersetzer->trans('ketteler.widerruf.zustimmung'),
        ];
        $event->setConvertedCart($bestellung);
    }
}
