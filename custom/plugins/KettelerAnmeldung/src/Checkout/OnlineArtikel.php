<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Checkout;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

/**
 * Online-Zugang oder Bundle (Zusatzfeld ketteler_artikelart). Für beide wird eine Lizenz vergeben,
 * deshalb nur mit Kundenkonto.
 */
final class OnlineArtikel
{
    public const ARTEN = ['online', 'bundle'];

    public static function istOnline(LineItem $position): bool
    {
        if ($position->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
            return false;
        }
        $felder = $position->getPayloadValue('customFields');

        return \is_array($felder) && \in_array($felder['ketteler_artikelart'] ?? null, self::ARTEN, true);
    }

    public static function enthaltenIn(LineItemCollection $positionen): bool
    {
        foreach ($positionen->getFlat() as $position) {
            if (self::istOnline($position)) {
                return true;
            }
        }

        return false;
    }
}
