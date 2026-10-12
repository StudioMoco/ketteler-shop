<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Nachricht;

/** Auftrag an die Warteschlange: für eine Bestellposition eine Lizenz anlegen oder stornieren. */
final class LizenzEreignis
{
    public const ANLEGEN = 'lizenz_anlegen';
    public const STORNIEREN = 'stornieren';

    public function __construct(
        public readonly string $orderId,
        public readonly string $positionId,
        public readonly string $art,
    ) {
    }

    /** Fest je Bestellposition und Art: Wiederholungen wirken in der Schnittstelle nicht doppelt. */
    public function ereignisId(): string
    {
        return 'order-' . $this->orderId . '-' . $this->positionId . '-' . ($this->art === self::ANLEGEN ? 'lizenz' : 'storno');
    }
}
