<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Nachricht;

/** Was der Lizenz-Ablauf von einer Bestellposition braucht. */
final class Position
{
    /** @param array<string, mixed> $lizenz Zusatzfeld ketteler_lizenz der Position */
    public function __construct(
        public readonly string $orderId,
        public readonly string $positionId,
        public readonly string $bestellnummer,
        public readonly string $bestelldatum,
        public readonly string $email,
        public readonly string $name,
        public readonly ?string $produkt,
        public readonly int $menge,
        public readonly bool $bestellungStorniert,
        public readonly array $lizenz,
    ) {
    }

    public function status(): string
    {
        return (string) ($this->lizenz['status'] ?? 'offen');
    }
}
