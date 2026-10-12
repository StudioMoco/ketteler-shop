<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Schnittstelle;

final class Antwort
{
    /** @param array<string, mixed> $daten */
    public function __construct(public readonly int $status, public readonly array $daten)
    {
    }
}
