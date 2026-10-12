<?php declare(strict_types=1);

namespace Ketteler\Mehrplatz;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

/**
 * Online-Artikel lassen sich nur einzeln kaufen. Für mehrere Lizenzen gibt es die Anfrage: Einrichtung,
 * Ansprechperson, E-Mail, Telefon, Anzahl, Nachricht. Sie geht an den Kundenservice und steht in der Verwaltung
 * (Kunden → Mehrplatz-Anfragen). Keine Bestellung, keine Lizenz.
 */
class KettelerMehrplatz extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        if ($uninstallContext->keepUserData()) {
            return;
        }
        $this->container->get(Connection::class)->executeStatement('DROP TABLE IF EXISTS `ketteler_mehrplatz_anfrage`');
    }
}
