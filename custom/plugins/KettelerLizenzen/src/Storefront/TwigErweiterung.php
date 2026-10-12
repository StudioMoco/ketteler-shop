<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Storefront;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Ketteler\Lizenzen\Nachricht\DalPositionenSpeicher;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigErweiterung extends AbstractExtension
{
    public function __construct(private readonly MeineWerke $meineWerke)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ketteler_bibliothek_url', self::bibliothekUrl(...)),
            new TwigFunction('ketteler_online_werk', static fn (mixed $position): ?string => $position instanceof OrderLineItemEntity ? DalPositionenSpeicher::werk($position) : null),
            new TwigFunction('ketteler_meine_werke', fn (?CustomerEntity $kunde): array => $kunde === null || $kunde->getGuest() ? [] : $this->meineWerke->fuerKunde($kunde->getId())),
        ];
    }

    /** Adresse der Bibliothek aus KETTELER_BIBLIOTHEK_URL, mit Werk: …/bibliothek/<werk> */
    public static function bibliothekUrl(?string $werk = null): string
    {
        $basis = $_SERVER['KETTELER_BIBLIOTHEK_URL'] ?? getenv('KETTELER_BIBLIOTHEK_URL');
        $basis = rtrim(\is_string($basis) && $basis !== '' ? $basis : 'https://ketteler-verlag.de/bibliothek', '/');

        return $werk !== null && $werk !== '' ? $basis . '/' . rawurlencode($werk) : $basis . '/';
    }
}
