<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Pflicht-Häkchen „sofortiger Beginn, Widerrufsrecht erlischt“ (§ 356 Abs. 5 BGB) für Privatkunden mit Online-Zugang
 * oder Bundle im Warenkorb. Text: ketteler.widerruf.zustimmung (von Ketteler zu prüfen).
 */
final class Widerruf
{
    public const FELD = 'ketteler_widerruf';

    public static function noetig(?Cart $cart, ?SalesChannelContext $context): bool
    {
        $kunde = $context?->getCustomer();

        return $cart !== null && $kunde !== null && !self::firma($kunde) && OnlineArtikel::enthaltenIn($cart->getLineItems());
    }

    private static function firma(CustomerEntity $kunde): bool
    {
        return $kunde->getAccountType() === CustomerEntity::ACCOUNT_TYPE_BUSINESS;
    }
}
