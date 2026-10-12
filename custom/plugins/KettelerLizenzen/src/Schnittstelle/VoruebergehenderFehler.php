<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Schnittstelle;

/** Schnittstelle nicht erreichbar, Zeitüberschreitung oder 5xx: später mit derselben ereignis_id erneut senden. */
class VoruebergehenderFehler extends \RuntimeException
{
}
