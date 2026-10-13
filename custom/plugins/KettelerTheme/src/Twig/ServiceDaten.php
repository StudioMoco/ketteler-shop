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
        'firma' => 'KETTELER-Verlag GmbH',
        'anschrift' => 'Schloßhof 1, 93449 Waldmünchen',
        'telefonNummer' => '09972-9414-51',
        'telefonWahl' => 'tel:+499972941451',
        'fax' => '09972-9414-55',
        'email' => 'kontakt@ketteler-verlag.de',
        'zeitenTage1' => 'Montag, Dienstag, Donnerstag',
        'zeitenUhr1' => 'von 8.00 bis 13.00 Uhr',
        'zeitenTage2' => 'Mittwoch und Freitag',
        'zeitenUhr2' => 'von 8.00 bis 12.00 Uhr',
        'fussZeile' => 'Klimafreundlicher Versand · GoGreen Plus',
        'bibliothekUrl' => '',
        'kontaktKategorie' => '',
    ];

    /** Logo: vorläufig PNG (480 × 94, doppelte Auflösung für 240 × 47) bis Ketteler eine SVG liefert; fehlt die Datei, das bisherige */
    public const LOGO_NEU = 'logo/ketteler-logo-fachverlag.png';
    public const LOGO_ALT = 'logo/ketteler-logo.png';


    public function __construct(
        private readonly SystemConfigService $config,
        private readonly UrlGeneratorInterface $router,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrls,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('kt_service', $this->daten(...)),
            new TwigFunction('kt_link', $this->link(...)),
            new TwigFunction('kt_werk_link', $this->werkLink(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('kt_kursiv', self::kursiv(...), ['is_safe' => ['html']]),
            new TwigFilter('kt_text', $this->text(...), ['is_safe' => ['html']]),
        ];
    }

    /** Ziel eines Block-Links: Kategorie oder Produkt über die SEO-Adresse, „bibliothek“, sonst die eingetragene Adresse; leer = null */
    public function link(mixed $ziel): ?string
    {
        if (!\is_string($ziel) || trim($ziel) === '') {
            return null;
        }
        $ziel = trim($ziel);
        if ($ziel === 'bibliothek') {
            return $this->daten()['bibliothekUrl'];
        }
        if (preg_match('/^(kategorie|produkt):([0-9a-f]{32})$/', $ziel, $t) && Uuid::isValid($t[2])) {
            return $t[1] === 'kategorie'
                ? $this->seoUrls->generate('frontend.navigation.page', ['navigationId' => $t[2]])
                : $this->seoUrls->generate('frontend.detail.page', ['productId' => $t[2]]);
        }
        // Nur eigene Pfade, http(s), mailto und tel; alles andere (z. B. javascript:) wird verworfen
        return preg_match('#^(/|https?://|mailto:|tel:|\#)#i', $ziel) ? $ziel : null;
    }

    /**
     * „Zum Angebot“ (Abos, Online, Print und Online): Seite des Werks, auf der die Variante gewählt wird. Werk aus dem
     * Zusatzfeld ketteler_produkt (avr, eichstaetter) oder aus den Kategorien (Zeitschrift ZMV); sonst die Artikelseite.
     */
    public function werkLink(object $produkt): string
    {
        $werk = $produkt->getTranslation('customFields')['ketteler_produkt'] ?? ($produkt->getCustomFields()['ketteler_produkt'] ?? null);
        $kategorien = array_merge($produkt->getCategoryIds() ?? [], $produkt->getCategoryTree() ?? []);
        if (!\is_string($werk) || $werk === '') {
            $zmv = array_map(static fn (string $k): string => Uuid::fromStringToHex('ketteler.kategorie.' . $k), ['zmv', 'zmv-print', 'zmv-digital', 'zmv-archiv']);
            $werk = array_intersect($zmv, $kategorien) !== [] ? 'zmv' : null;
        }
        if (\is_string($werk) && \in_array($werk, ['avr', 'eichstaetter', 'zmv'], true)) {
            return $this->seoUrls->generate('frontend.navigation.page', ['navigationId' => Uuid::fromStringToHex('ketteler.kategorie.' . $werk)]);
        }

        return $this->seoUrls->generate('frontend.detail.page', ['productId' => $produkt->getId()]);
    }

    public static function kursiv(mixed $text): string
    {
        $sicher = htmlspecialchars((string) $text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

        return (string) preg_replace('/\*([^*]+)\*/u', '<em>$1</em>', $sicher);
    }

    /**
     * Kurzer gepflegter Text mit wenigen Auszeichnungen: **fett**, *kursiv*, [Linktext](Ziel). Ziel wie bei Block-Links
     * (kategorie:<id>, produkt:<id>, /pfad, https://…, mailto:); unbekannte Ziele bleiben reiner Text. Alles andere wird
     * maskiert, Zeilenumbrüche werden zu <br>.
     */
    public function text(mixed $text): string
    {
        $sicher = htmlspecialchars((string) $text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        $sicher = (string) preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function (array $t): string {
            $ziel = $this->link(html_entity_decode($t[2], \ENT_QUOTES, 'UTF-8'));

            return $ziel === null ? $t[1] : '<a class="kt-fliesslink" href="' . htmlspecialchars($ziel, \ENT_QUOTES, 'UTF-8') . '">' . $t[1] . '</a>';
        }, $sicher);
        $sicher = (string) preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $sicher);
        $sicher = (string) preg_replace('/\*([^*]+)\*/u', '<em>$1</em>', $sicher);

        return nl2br($sicher, false);
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
        $werte['servicezeiten'] = $werte['zeitenTage1'] . ' ' . $werte['zeitenUhr1'] . ', ' . $werte['zeitenTage2'] . ' ' . $werte['zeitenUhr2'];
        $neu = is_file(__DIR__ . '/../Resources/public/' . self::LOGO_NEU);
        $werte['logo'] = 'bundles/kettelertheme/' . ($neu ? self::LOGO_NEU : self::LOGO_ALT);
        $werte['logoBreite'] = $neu ? '240' : '218';
        $werte['logoHoehe'] = $neu ? '47' : '57';
        $werte['kontaktUrl'] = $werte['kontaktKategorie'] !== ''
            ? $this->seoUrls->generate('frontend.navigation.page', ['navigationId' => $werte['kontaktKategorie']])
            : 'mailto:' . $werte['email'];

        return $werte;
    }
}
