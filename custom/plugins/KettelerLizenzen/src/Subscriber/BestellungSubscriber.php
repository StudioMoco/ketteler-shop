<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Subscriber;

use Ketteler\Lizenzen\Nachricht\DalPositionenSpeicher;
use Ketteler\Lizenzen\Nachricht\LizenzEreignis;
use Ketteler\Lizenzen\Nachricht\PositionenSpeicher;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Bestellung abgeschlossen → je Online-Position eine Lizenz anlegen (sofort, auch bei Rechnung).
 * Bestellung storniert → Lizenzen der Bestellung stornieren.
 * Gesendet wird immer über die Warteschlange, damit ein Ausfall der Schnittstelle die Bestellung nicht stört.
 */
class BestellungSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly PositionenSpeicher $positionen,
        private readonly EntityRepository $bestellungen,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'bestellt',
            'state_enter.order.state.cancelled' => 'storniert',
        ];
    }

    public function bestellt(CheckoutOrderPlacedEvent $event): void
    {
        $this->fuerOnlinePositionen($event->getOrder(), function (OrderLineItemEntity $position) use ($event): void {
            $this->positionen->speichern($position->getId(), ['status' => 'offen', 'werk' => DalPositionenSpeicher::werk($position)]);
            $this->bus->dispatch(new LizenzEreignis($event->getOrderId(), $position->getId(), LizenzEreignis::ANLEGEN));
        });
    }

    public function storniert(OrderStateMachineStateChangeEvent $event): void
    {
        $orderId = $event->getOrderId();
        $bestellung = $this->bestellungen->search((new Criteria([$orderId]))->addAssociation('lineItems'), Context::createDefaultContext())->first();
        if (!$bestellung instanceof OrderEntity) {
            return;
        }
        $this->fuerOnlinePositionen($bestellung, function (OrderLineItemEntity $position) use ($orderId): void {
            $this->bus->dispatch(new LizenzEreignis($orderId, $position->getId(), LizenzEreignis::STORNIEREN));
        });
    }

    /** @param callable(OrderLineItemEntity): void $aktion */
    private function fuerOnlinePositionen(OrderEntity $bestellung, callable $aktion): void
    {
        foreach ($bestellung->getLineItems() ?? [] as $position) {
            if (DalPositionenSpeicher::werk($position) !== null) {
                $aktion($position);
            }
        }
    }
}
