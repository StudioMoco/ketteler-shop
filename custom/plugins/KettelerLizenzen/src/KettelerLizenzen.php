<?php declare(strict_types=1);

namespace Ketteler\Lizenzen;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Online-Kauf → Lizenz im Redaktionssystem. Ablauf und Schnittstelle: docs/plan.md, Abschnitt 1.
 * Der Stand je Bestellposition steht im Zusatzfeld ketteler_lizenz der Position.
 */
class KettelerLizenzen extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        // Resources/config/packages/*.yaml: eigene Warteschlange ketteler_lizenz mit Wiederholungsregel
        $this->buildDefaultConfig($container);
    }
}
