<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Nachricht;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class DalPositionenSpeicher implements PositionenSpeicher
{
    public const FELD = 'ketteler_lizenz';

    public function __construct(
        private readonly EntityRepository $bestellungen,
        private readonly EntityRepository $positionen,
    ) {
    }

    public function laden(string $orderId, string $positionId): ?Position
    {
        $criteria = (new Criteria([$orderId]))->addAssociation('lineItems')->addAssociation('orderCustomer')->addAssociation('stateMachineState');
        $bestellung = $this->bestellungen->search($criteria, Context::createDefaultContext())->first();
        if (!$bestellung instanceof OrderEntity) {
            return null;
        }
        $position = $bestellung->getLineItems()?->get($positionId);
        if (!$position instanceof OrderLineItemEntity) {
            return null;
        }
        $kunde = $bestellung->getOrderCustomer();

        return new Position(
            $orderId,
            $positionId,
            (string) $bestellung->getOrderNumber(),
            $bestellung->getOrderDateTime()->format('Y-m-d'),
            mb_strtolower((string) $kunde?->getEmail()),
            trim(($kunde?->getFirstName() ?? '') . ' ' . ($kunde?->getLastName() ?? '')),
            self::werk($position),
            $position->getQuantity(),
            $bestellung->getStateMachineState()?->getTechnicalName() === OrderStates::STATE_CANCELLED,
            (array) ($position->getCustomFields()[self::FELD] ?? []),
        );
    }

    public function speichern(string $positionId, array $lizenz): void
    {
        $context = Context::createDefaultContext();
        $position = $this->positionen->search(new Criteria([$positionId]), $context)->first();
        $bisher = $position instanceof OrderLineItemEntity ? (array) ($position->getCustomFields()[self::FELD] ?? []) : [];
        $neu = array_filter(array_merge($bisher, $lizenz, ['geaendert' => (new \DateTimeImmutable())->format(\DATE_ATOM)]), static fn ($w) => $w !== null);
        $this->positionen->update([['id' => $positionId, 'customFields' => [self::FELD => $neu]]], $context);
    }

    /** Online-Werk der Position (avr, eichstaetter) bei Online-Zugang und Bundle, sonst null. */
    public static function werk(OrderLineItemEntity $position): ?string
    {
        $felder = $position->getPayload()['customFields'] ?? [];
        if (!\is_array($felder) || !\in_array($felder['ketteler_artikelart'] ?? null, ['online', 'bundle'], true)) {
            return null;
        }
        $werk = $felder['ketteler_produkt'] ?? null;

        return \is_string($werk) && $werk !== '' ? $werk : null;
    }
}
