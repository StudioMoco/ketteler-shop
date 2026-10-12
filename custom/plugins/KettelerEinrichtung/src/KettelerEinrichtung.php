<?php declare(strict_types=1);

namespace Ketteler\Einrichtung;

use Shopware\Core\Framework\Plugin;

/**
 * Enthält nur den Befehl ketteler:einrichten. Alles, was er anlegt, hat feste IDs:
 * ein frischer Aufbau ergibt denselben Stand, ein zweiter Lauf ändert nichts.
 */
class KettelerEinrichtung extends Plugin
{
}
