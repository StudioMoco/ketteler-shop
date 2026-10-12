<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Nachricht;

interface PositionenSpeicher
{
    public function laden(string $orderId, string $positionId): ?Position;

    /** @param array<string, mixed> $lizenz neuer Stand, wird mit dem bisherigen zusammengeführt */
    public function speichern(string $positionId, array $lizenz): void;
}
