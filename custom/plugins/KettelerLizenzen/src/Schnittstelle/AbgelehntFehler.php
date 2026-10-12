<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Schnittstelle;

/** 4xx: nicht wiederholen, sondern der Verwaltung melden. */
class AbgelehntFehler extends \RuntimeException
{
    public function __construct(string $meldung, public readonly int $status)
    {
        parent::__construct($meldung);
    }
}
