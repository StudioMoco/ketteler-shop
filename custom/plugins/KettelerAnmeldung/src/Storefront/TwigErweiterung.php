<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Storefront;

use Ketteler\Anmeldung\Checkout\OnlineArtikel;
use Ketteler\Anmeldung\Oidc\Konfiguration;
use Shopware\Core\Checkout\Cart\Cart;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigErweiterung extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ketteler_hat_online', static fn (?Cart $cart): bool => $cart !== null && OnlineArtikel::enthaltenIn($cart->getLineItems())),
            new TwigFunction('ketteler_zitadel_konto_url', static fn (): string => rtrim(Konfiguration::env('KETTELER_ZITADEL_URL'), '/') . '/ui/console/users/me'),
        ];
    }
}
