<?php declare(strict_types=1);

namespace Ketteler\Theme\Cookie;

use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;

/**
 * Cookie-Hinweis nur mit technisch notwendigen Cookies (Gesamtauftrag D): Der Shop setzt keine Statistik-, Marketing- oder
 * Komfort-Cookies, also bietet der Hinweis auch keine an. Bleibt nur die Gruppe „Technisch erforderlich“ von Shopware.
 * Folge: Die Merkliste speichert für Gäste nicht im Browser; angemeldete Kundinnen nutzen sie wie gewohnt.
 */
class NurNotwendige implements CookieProviderInterface
{
    public function __construct(private readonly CookieProviderInterface $original)
    {
    }

    public function getCookieGroups(): array
    {
        return array_values(array_filter(
            $this->original->getCookieGroups(),
            static fn (array $gruppe): bool => ($gruppe['isRequired'] ?? false) === true,
        ));
    }
}
