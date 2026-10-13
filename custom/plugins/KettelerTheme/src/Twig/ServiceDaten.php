<?php declare(strict_types=1);

namespace Ketteler\Theme\Twig;

use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Kontakt- und Servicedaten für Kopf und Fuß aus einer Stelle: Erweiterungen > Ketteler Theme > Konfigurieren.
 * Nicht gespeicherte Felder fallen auf die Vorgaben zurück (gleich der config.xml), damit nie ein leerer Kopf entsteht.
 * Dazu kt_link (Links der Erlebniswelt-Blöcke: „kategorie:<id>“, „produkt:<id>“ oder Adresse) und kt_kursiv
 * („*Wort*“ wird kursiv, sonst reiner Text).
 */
class ServiceDaten extends AbstractExtension
{
    public const VORGABEN = [
        'telefon' => '09972 9414-51',
        'telefonLink' => '+499972941451',
        'servicezeiten' => 'Mo, Di, Do 8 bis 13 Uhr, Mi und Fr 8 bis 12 Uhr',
        'email' => 'kontakt@ketteler-verlag.de',
        'bibliothekUrl' => '',
        'kontaktKategorie' => '',
        'adresse' => 'KETTELER-Verlag GmbH · Schloßhof 1 · 93449 Waldmünchen',
        'fussHinweis' => 'Klimafreundlicher Versand mit GoGreen Plus · Alle Preise inkl. MwSt.',
    ];

    public function __construct(
        private readonly SystemConfigService $config,
        private readonly UrlGeneratorInterface $router,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrls,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('kt_service', $this->daten(...)), new TwigFunction('kt_link', $this->link(...))];
    }

    public function getFilters(): array
    {
        return [new TwigFilter('kt_kursiv', self::kursiv(...), ['is_safe' => ['html']])];
    }

    /** Ziel eines Block-Links: Kategorie oder Produkt über die SEO-Adresse, sonst die eingetragene Adresse; leer = null */
    public function link(mixed $ziel): ?string
    {
        if (!\is_string($ziel) || trim($ziel) === '') {
            return null;
        }
        $ziel = trim($ziel);
        if (preg_match('/^(kategorie|produkt):([0-9a-f]{32})$/', $ziel, $t) && Uuid::isValid($t[2])) {
            return $t[1] === 'kategorie'
                ? $this->seoUrls->generate('frontend.navigation.page', ['navigationId' => $t[2]])
                : $this->seoUrls->generate('frontend.detail.page', ['productId' => $t[2]]);
        }
        // Nur eigene Pfade, http(s), mailto und tel; alles andere (z. B. javascript:) wird verworfen
        return preg_match('#^(/|https?://|mailto:|tel:|\#)#i', $ziel) ? $ziel : null;
    }

    public static function kursiv(mixed $text): string
    {
        $sicher = htmlspecialchars((string) $text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        return (string) preg_replace('/\*([^*]+)\*/u', '<em>$1</em>', $sicher);
    }

    /** @return array<string, string> */
    public function daten(?string $salesChannelId = null): array
    {
        $werte = [];
        foreach (self::VORGABEN as $feld => $vorgabe) {
            $wert = $this->config->get('KettelerTheme.config.' . $feld, $salesChannelId);
            $werte[$feld] = \is_string($wert) && trim($wert) !== '' ? trim($wert) : $vorgabe;
        }
        if ($werte['bibliothekUrl'] === '') {
            $werte['bibliothekUrl'] = rtrim((string) ($_SERVER['KETTELER_BIBLIOTHEK_URL'] ?? getenv('KETTELER_BIBLIOTHEK_URL') ?: ''), '/') . '/';
        }
        $werte['kontaktUrl'] = $werte['kontaktKategorie'] !== ''
            ? $this->router->generate('frontend.navigation.page', ['navigationId' => $werte['kontaktKategorie']])
            : 'mailto:' . $werte['email'];

        return $werte;
    }
}
