<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Nachricht;

/** Meldet der Verwaltung, wenn eine Lizenz nicht automatisch angelegt oder storniert werden konnte. */
interface Meldung
{
    public function melden(Position $position, string $anlass, string $text): void;
}
