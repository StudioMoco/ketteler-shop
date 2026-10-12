<?php declare(strict_types=1);

namespace Ketteler\Steuern\Ustid;

use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Bestellung ohne Umsatzsteuer wegen bestätigter USt-IdNr.: den Nachweis der VIES-Prüfung (Zeit, Ergebnis,
 * Abfragenummer) an der Bestellung festhalten (Zusatzfeld ketteler_ustid_nachweis). So bleibt für jede Rechnung
 * erkennbar, welche Bestätigung galt, auch wenn die Nummer am Kunden später neu geprüft wird.
 */
class NachweisSubscriber implements EventSubscriberInterface
{
    public const FELD = 'ketteler_ustid_nachweis';

    public function __construct(private readonly UstIdPruefung $pruefung)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [CartConvertedEvent::class => 'umgewandelt'];
    }

    public function umgewandelt(CartConvertedEvent $event): void
    {
        $bestellung = $event->getConvertedCart();
        $kunde = $event->getSalesChannelContext()->getCustomer();
        // Wurde in dieser Anfrage neu geprüft (Bestätigung älter als ein Jahr), gilt der neue Stand
        $stand = $kunde === null ? null : $this->pruefung->aktuellerStand($kunde->getId(), $kunde->getCustomFields()[UstIdPruefung::FELD] ?? null);
        if (($event->getCart()->getPrice()->getTaxStatus()) !== CartPrice::TAX_STATE_FREE || !\is_array($stand)) {
            return;
        }
        $bestellung['customFields'] = ($bestellung['customFields'] ?? []) + [
            self::FELD => array_intersect_key($stand, array_flip(['status', 'ustid', 'land', 'geprueft', 'name', 'anfrageNummer', 'viesDatum', 'anfragende'])),
        ];
        $event->setConvertedCart($bestellung);
    }
}
