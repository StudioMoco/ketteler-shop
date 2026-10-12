<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Gastbestellung nur bei reinem Print. Liegt ein Online-Artikel oder Bundle im Warenkorb und ist der Kunde ein Gast,
 * wird die Bestellung blockiert. Gilt für jeden Weg (Storefront und Store-API).
 */
class OnlineNurMitKonto implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        $kunde = $context->getCustomer();
        if ($kunde === null || !$kunde->getGuest()) {
            return;
        }
        if (OnlineArtikel::enthaltenIn($cart->getLineItems())) {
            $errors->add(new OnlineNurMitKontoFehler());
        }
    }
}
