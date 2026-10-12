<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Storefront;

use Ketteler\Anmeldung\Checkout\OnlineArtikel;
use Ketteler\Anmeldung\Oidc\Konfiguration;
use Shopware\Core\Checkout\Cart\Cart;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigErweiterung extends AbstractExtension
{
    public function __construct(private readonly UrlGeneratorInterface $router)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ketteler_ziel_aus_route', $this->zielAusRoute(...)),
            new TwigFunction('ketteler_hat_online', static fn (?Cart $cart): bool => $cart !== null && OnlineArtikel::enthaltenIn($cart->getLineItems())),
            new TwigFunction('ketteler_zitadel_konto_url', static fn (): string => rtrim(Konfiguration::env('KETTELER_ZITADEL_URL'), '/') . '/ui/console/users/me'),
        ];
    }

    /** Shopware gibt beim Login-Zwang einen Routennamen mit (redirectTo); daraus wird ein Pfad im Shop. */
    public function zielAusRoute(mixed $route): ?string
    {
        if (!\is_string($route) || !str_starts_with($route, 'frontend.')) {
            return null;
        }
        try {
            return AnmeldungController::sicheresZiel($this->router->generate($route));
        } catch (\Throwable) {
            return null;
        }
    }
}
