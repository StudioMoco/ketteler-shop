<?php declare(strict_types=1);

namespace Ketteler\Einrichtung\Command;

use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Legt die Erlebniswelten aus daten/erlebniswelten.json an (Startseite, Sortiment) und weist sie ihren Kategorien zu.
 * Nur EINMAL: Gibt es die Seite schon, bleibt sie unverändert, weil Ketteler sie danach in der Verwaltung pflegt und
 * ketteler:einrichten bei jeder Auslieferung läuft. Neu anlegen (überschreibt die Pflege!) nur mit --erlebniswelten-neu.
 * Medien (Platzhalter-Video, Vorschaubild, Untertitel) kommen aus daten/medien, ebenfalls nur einmal.
 *
 * Gemeinsame Layouts (z. B. „Werkseite“ für ZMV, Eichstätter und AVR): EIN Layout mit festen Plätzen, die Inhalte je
 * Kategorie stehen in deren Layout-Inhalten (category.slotConfig, in der Verwaltung: Kategorie > Layout). Leere Plätze
 * zeigen nichts. Auch hier gilt: nur einmal anlegen, gepflegte Inhalte bleiben (außer mit --erlebniswelten-neu).
 * PDF-Platzhalter (@pdf:Titel) entstehen als kleine PDF-Datei mit dem Titel, bis Ketteler die echte Datei hochlädt.
 */
class Erlebniswelten
{
    private const DATEN = __DIR__ . '/../Resources/daten/';

    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly MediaService $medien,
    ) {
    }

    public static function id(string $schluessel): string
    {
        return Uuid::fromStringToHex('ketteler.' . $schluessel);
    }

    public function anlegen(Context $context, SymfonyStyle $io, bool $neu): void
    {
        $daten = json_decode((string) file_get_contents(self::DATEN . 'erlebniswelten.json'), true, 512, \JSON_THROW_ON_ERROR);
        // Nicht mehr vorgesehene Seiten entfernen, sofern keine Kategorie sie mehr nutzt
        foreach ($daten['entfernt'] ?? [] as $schluessel) {
            $alt = self::id('cms.' . $schluessel);
            $genutzt = $this->repo('category')->searchIds((new Criteria())->addFilter(new EqualsFilter('cmsPageId', $alt)), $context)->getTotal() > 0;
            if (!$genutzt && $this->repo('cms_page')->searchIds(new Criteria([$alt]), $context)->firstId() !== null) {
                $this->repo('cms_page')->delete([['id' => $alt]], $context);
                $io->writeln(\sprintf('Erlebniswelt „%s“ entfernt.', $schluessel));
            }
        }
        foreach ($daten['seiten'] as $seite) {
            $id = self::id('cms.' . $seite['schluessel']);
            $kategorien = array_map(static fn (string $k): string => self::id('kategorie.' . $k), (array) ($seite['kategorien'] ?? $seite['kategorie']));
            $vorhanden = $this->repo('cms_page')->searchIds(new Criteria([$id]), $context)->firstId() !== null;
            if ($vorhanden && !$neu) {
                $io->writeln(\sprintf('Erlebniswelt „%s“ besteht schon und bleibt, wie sie in der Verwaltung gepflegt ist.', $seite['name']));
                foreach ($kategorien as $kategorie) {
                    $this->zuweisen($kategorie, $id, $context, false);
                }
                continue;
            }
            if ($vorhanden) {
                // Kategorien hängen an der Seite (Löschen sonst gesperrt): erst lösen, dann neu anlegen
                $nutzer = $this->repo('category')->searchIds((new Criteria())->addFilter(new EqualsFilter('cmsPageId', $id)), $context)->getIds();
                if ($nutzer !== []) {
                    $this->repo('category')->update(array_map(static fn (string $k): array => ['id' => $k, 'cmsPageId' => null], $nutzer), $context);
                }
                $this->repo('cms_page')->delete([['id' => $id]], $context);
            }
            $this->repo('cms_page')->create([$this->seite($id, $seite, $context)], $context);
            foreach ($kategorien as $kategorie) {
                $this->zuweisen($kategorie, $id, $context, true);
            }
            $io->writeln(\sprintf('Erlebniswelt „%s“ angelegt (%d Blöcke).', $seite['name'], \count($seite['bloecke'])));
        }
        foreach ($daten['layouts'] ?? [] as $layout) {
            $this->layout($layout, $context, $io, $neu);
        }
    }

    /**
     * Gemeinsames Layout mit festen Plätzen (ein Block je Typ, in der Reihenfolge der Daten) und Inhalten je Kategorie.
     *
     * @param array<string, mixed> $layout
     */
    private function layout(array $layout, Context $context, SymfonyStyle $io, bool $neu): void
    {
        $id = self::id('cms.' . $layout['schluessel']);
        $vorhanden = $this->repo('cms_page')->searchIds(new Criteria([$id]), $context)->firstId() !== null;
        if (!$vorhanden || $neu) {
            $bloecke = [];
            foreach ($layout['bloecke'] as $i => $typ) {
                $bloecke[] = [
                    'id' => self::id('cms.' . $layout['schluessel'] . '.block.' . $typ),
                    'type' => 'ketteler-' . $typ,
                    'position' => $i,
                    'sectionPosition' => 'main',
                    'marginTop' => '0',
                    'marginBottom' => '0',
                    'marginLeft' => '0',
                    'marginRight' => '0',
                    'slots' => [[
                        'id' => $this->platz($layout['schluessel'], $typ),
                        'type' => 'ketteler-' . $typ,
                        'slot' => 'inhalt',
                        'config' => [],
                    ]],
                ];
            }
            // Bestehendes Layout ersetzen: Kategorien behalten ihre Zuordnung (gleiche ID), Plätze haben feste IDs
            $this->repo('cms_page')->upsert([[
                'id' => $id,
                'type' => 'page',
                'name' => $layout['name'],
                'sections' => [[
                    'id' => self::id('cms.' . $layout['schluessel'] . '.bereich'),
                    'type' => 'default',
                    'position' => 0,
                    'sizingMode' => 'full_width',
                    'blocks' => $bloecke,
                ]],
            ]], $context);
            $io->writeln(\sprintf('Layout „%s“ angelegt (%d Plätze).', $layout['name'], \count($bloecke)));
        }
        foreach ($layout['inhalte'] as $schluessel => $inhalte) {
            $kategorie = self::id('kategorie.' . $schluessel);
            $k = $this->repo('category')->search(new Criteria([$kategorie]), $context)->first();
            if ($k === null) {
                continue;
            }
            if (!$neu && $k->getCmsPageId() !== null && !empty($k->getSlotConfig())) {
                $io->writeln(\sprintf('Inhalte der Kategorie „%s“ bestehen schon und bleiben.', $k->getName()));
                continue;
            }
            $slotConfig = [];
            foreach ($layout['bloecke'] as $typ) {
                $konfig = [];
                foreach ($inhalte[$typ] ?? [] as $feld => $wert) {
                    $konfig[$feld] = ['source' => 'static', 'value' => $this->aufloesen($wert, $context)];
                }
                // Auch leere Plätze ausdrücklich leer setzen, damit nichts aus dem Layout durchscheint
                $slotConfig[$this->platz($layout['schluessel'], $typ)] = $konfig;
            }
            $this->repo('category')->update([['id' => $kategorie, 'cmsPageId' => $id, 'slotConfig' => $slotConfig]], $context);
            $io->writeln(\sprintf('Kategorie „%s“: Layout „%s“ mit eigenen Inhalten.', $k->getName(), $layout['name']));
        }
    }

    private function platz(string $layout, string $typ): string
    {
        return self::id('cms.' . $layout . '.block.' . $typ . '.inhalt');
    }

    /** Seite an die Kategorie hängen; ohne $immer nur, wenn die Kategorie noch keine eigene Erlebniswelt hat */
    private function zuweisen(string $kategorie, string $seite, Context $context, bool $immer): void
    {
        $k = $this->repo('category')->search(new Criteria([$kategorie]), $context)->first();
        if ($k !== null && ($immer || $k->getCmsPageId() === null)) {
            $this->repo('category')->update([['id' => $kategorie, 'cmsPageId' => $seite]], $context);
        }
    }

    /**
     * @param array<string, mixed> $seite
     *
     * @return array<string, mixed>
     */
    private function seite(string $id, array $seite, Context $context): array
    {
        $bloecke = [];
        $position = 0;
        if (!empty($seite['titel'])) {
            // Eigene Seite ohne Schaufenster: Hauptüberschrift als Textblock (genau ein h1 je Seite)
            $bloecke[] = [
                'id' => self::id('cms.' . $seite['schluessel'] . '.titel'),
                'type' => 'text',
                'position' => $position++,
                'sectionPosition' => 'main',
                'marginTop' => '40px',
                'marginBottom' => '0',
                'marginLeft' => '24px',
                'marginRight' => '24px',
                'slots' => [[
                    'id' => self::id('cms.' . $seite['schluessel'] . '.titel.text'),
                    'type' => 'text',
                    'slot' => 'content',
                    'config' => ['content' => ['source' => 'static', 'value' => '<h1>' . htmlspecialchars($seite['titel']) . '</h1>']],
                ]],
            ];
        }
        foreach ($seite['bloecke'] as $i => $block) {
            if ($block['typ'] === 'html') {
                // Fließtext (Verlag, Links) im Textelement von Shopware, in der Breite der Vorlagen
                $bloecke[] = [
                    'id' => self::id('cms.' . $seite['schluessel'] . '.block.' . $i),
                    'type' => 'text',
                    'position' => $position++,
                    'sectionPosition' => 'main',
                    'cssClass' => 'kt-textseite',
                    'marginTop' => '0',
                    'marginBottom' => '88px',
                    'marginLeft' => '0',
                    'marginRight' => '0',
                    'slots' => [[
                        'id' => self::id('cms.' . $seite['schluessel'] . '.block.' . $i . '.text'),
                        'type' => 'text',
                        'slot' => 'content',
                        'config' => ['content' => ['source' => 'static', 'value' => $this->aufloesenHtml($block['html'])]],
                    ]],
                ];
                continue;
            }
            if ($block['typ'] === 'kontaktformular') {
                // Kontaktformular von Shopware; Empfänger aus KETTELER_KONTAKT_EMAIL (auf Staging: Mailpit)
                $an = trim((string) ($_SERVER['KETTELER_KONTAKT_EMAIL'] ?? getenv('KETTELER_KONTAKT_EMAIL') ?: 'kontakt@ketteler-verlag.de'));
                $bloecke[] = [
                    'id' => self::id('cms.' . $seite['schluessel'] . '.block.' . $i),
                    'type' => 'form',
                    'position' => $position++,
                    'sectionPosition' => 'main',
                    'cssClass' => 'kt-kontaktformular',
                    'marginTop' => '0',
                    'marginBottom' => '96px',
                    'marginLeft' => '0',
                    'marginRight' => '0',
                    'slots' => [[
                        'id' => self::id('cms.' . $seite['schluessel'] . '.block.' . $i . '.formular'),
                        'type' => 'form',
                        'slot' => 'content',
                        'config' => [
                            'type' => ['source' => 'static', 'value' => 'contact'],
                            'title' => ['source' => 'static', 'value' => $block['titel'] ?? ''],
                            'mailReceiver' => ['source' => 'static', 'value' => [$an]],
                            'defaultMailReceiver' => ['source' => 'static', 'value' => false],
                            'confirmationText' => ['source' => 'static', 'value' => $block['bestaetigung'] ?? ''],
                        ],
                    ]],
                ];
                continue;
            }
            $typ = 'ketteler-' . $block['typ'];
            $konfig = [];
            foreach ($block['config'] as $feld => $wert) {
                $konfig[$feld] = ['source' => 'static', 'value' => $this->aufloesen($wert, $context)];
            }
            $bloecke[] = [
                'id' => self::id('cms.' . $seite['schluessel'] . '.block.' . $i),
                'type' => $typ,
                'position' => $position++,
                'sectionPosition' => 'main',
                'marginTop' => '0',
                'marginBottom' => '0',
                'marginLeft' => '0',
                'marginRight' => '0',
                'slots' => [[
                    'id' => self::id('cms.' . $seite['schluessel'] . '.block.' . $i . '.inhalt'),
                    'type' => $typ,
                    'slot' => 'inhalt',
                    'config' => $konfig,
                ]],
            ];
        }

        return [
            'id' => $id,
            'type' => 'page', // in der Verwaltung „Shopseite“
            'name' => $seite['name'],
            'sections' => [[
                'id' => self::id('cms.' . $seite['schluessel'] . '.bereich'),
                'type' => 'default',
                'position' => 0,
                'sizingMode' => 'full_width',
                'blocks' => $bloecke,
            ]],
        ];
    }

    /** Verweise in den Daten durch IDs bzw. Link-Ziele ersetzen (auch in Listen) */
    private function aufloesen(mixed $wert, Context $context): mixed
    {
        if (\is_array($wert)) {
            return array_map(fn (mixed $w): mixed => $this->aufloesen($w, $context), $wert);
        }
        if (\is_string($wert) && str_contains($wert, '](@')) {
            // Links im Text: [AGB](@link-kategorie:fuss-agb)
            return (string) preg_replace_callback('/\]\((@[a-z-]+:[^)]+)\)/', fn (array $t): string => '](' . $this->aufloesen($t[1], $context) . ')', $wert);
        }
        if (!\is_string($wert) || !str_starts_with($wert, '@')) {
            return $wert;
        }
        [$art, $schluessel] = explode(':', substr($wert, 1), 2);

        return match ($art) {
            'artikel' => self::id('artikel.' . $schluessel),
            'kategorie' => self::id('kategorie.' . $schluessel),
            'link-kategorie' => 'kategorie:' . self::id('kategorie.' . $schluessel),
            'link-artikel' => 'produkt:' . self::id('artikel.' . $schluessel),
            'medium' => $this->medium($schluessel, $context),
            'pdf' => $this->pdf($schluessel, $context),
            'bild' => $this->bild($schluessel, $context),
            default => throw new \RuntimeException('Unbekannter Verweis: ' . $wert),
        };
    }

    /** Medium aus daten/medien einmal hochladen; fehlt die Datei, bleibt das Feld leer (z. B. kein Video) */
    private function medium(string $datei, Context $context): ?string
    {
        $pfad = self::DATEN . 'medien/' . $datei;
        if (!is_file($pfad)) {
            return null;
        }
        $id = self::id('medium.' . $datei);
        if ($this->repo('media')->searchIds(new Criteria([$id]), $context)->firstId() !== null) {
            return $id;
        }
        $endung = strtolower(pathinfo($datei, \PATHINFO_EXTENSION));
        $typ = ['mp4' => 'video/mp4', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'vtt' => 'text/vtt'][$endung] ?? 'application/octet-stream';
        // Feste ID, im Standardordner der Erlebniswelten (Medien > „CMS Media“)
        $ordner = $this->repo('media_folder')->searchIds((new Criteria())->addFilter(new EqualsFilter('defaultFolder.entity', 'cms_page'))->setLimit(1), $context)->firstId();
        $this->repo('media')->create([['id' => $id, 'mediaFolderId' => $ordner, 'title' => pathinfo($datei, \PATHINFO_FILENAME)]], $context);
        $this->medien->saveFile((string) file_get_contents($pfad), $endung, $typ, pathinfo($datei, \PATHINFO_FILENAME), $context, null, $id);

        return $id;
    }

    /**
     * Platzhalter-PDF mit dem Titel der Datei (die echte Datei liefert Ketteler, siehe docs/plan.md). Einmal anlegen;
     * ersetzt Ketteler die Datei in der Medienverwaltung, bleibt der Verweis gültig.
     */
    private function pdf(string $titel, Context $context): string
    {
        $id = self::id('pdf.' . $titel);
        if ($this->repo('media')->searchIds(new Criteria([$id]), $context)->firstId() !== null) {
            return $id;
        }
        $ordner = $this->repo('media_folder')->searchIds((new Criteria())->addFilter(new EqualsFilter('defaultFolder.entity', 'cms_page'))->setLimit(1), $context)->firstId();
        $this->repo('media')->create([['id' => $id, 'mediaFolderId' => $ordner, 'title' => $titel, 'alt' => $titel]], $context);
        $name = 'platzhalter-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(strtr($titel, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', '§' => 'paragraf']))), '-');
        $this->medien->saveFile(self::platzhalterPdf($titel), 'pdf', 'application/pdf', substr($name, 0, 80) . '-' . substr($id, 0, 6), $context, null, $id);

        return $id;
    }

    /**
     * Link-Ziele im HTML ersetzen: href="@link-kategorie:verlag" wird zum SEO-Platzhalter von Shopware, den der Shop beim
     * Ausliefern durch die Adresse der Kategorie ersetzt (wie Links, die man in der Verwaltung im Text setzt)
     */
    private function aufloesenHtml(string $html): string
    {
        return (string) preg_replace_callback('/href="@link-kategorie:([a-z0-9-]+)"/', static fn (array $t): string => 'href="124c71d524604ccbad6042edce3ac799/navigation/' . self::id('kategorie.' . $t[1]) . '#"', $html);
    }

    /** Platzhalterbild (Sand) mit Alt-Text, z. B. für Fotos von Personen, bis Ketteler das echte Bild liefert */
    private function bild(string $alt, Context $context): string
    {
        $id = self::id('bild.' . $alt);
        if ($this->repo('media')->searchIds(new Criteria([$id]), $context)->firstId() !== null) {
            return $id;
        }
        $ordner = $this->repo('media_folder')->searchIds((new Criteria())->addFilter(new EqualsFilter('defaultFolder.entity', 'cms_page'))->setLimit(1), $context)->firstId();
        $this->repo('media')->create([['id' => $id, 'mediaFolderId' => $ordner, 'title' => 'Platzhalter: ' . $alt, 'alt' => $alt]], $context);
        $name = 'platzhalter-foto-' . substr($id, 0, 10);
        $this->medien->saveFile((string) file_get_contents(self::DATEN . 'medien/platzhalter-sand.png'), 'png', 'image/png', $name, $context, null, $id);

        return $id;
    }

    /** Einseitiges PDF mit zwei Zeilen Text (Helvetica, WinAnsi), ohne Bibliothek */
    public static function platzhalterPdf(string $titel): string
    {
        $text = static fn (string $t): string => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) mb_convert_encoding($t, 'Windows-1252', 'UTF-8'));
        $inhalt = "BT /F1 16 Tf 72 760 Td (" . $text($titel) . ") Tj 0 -28 Td /F1 11 Tf (" . $text('Platzhalter: Die Datei liefert der KETTELER-Verlag.') . ") Tj ET";
        $objekte = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length ' . \strlen($inhalt) . " >>\nstream\n" . $inhalt . "\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $stellen = [];
        foreach ($objekte as $i => $o) {
            $stellen[] = \strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $o . "\nendobj\n";
        }
        $xref = \strlen($pdf);
        $pdf .= "xref\n0 " . (\count($objekte) + 1) . "\n0000000000 65535 f \n";
        foreach ($stellen as $s) {
            $pdf .= \sprintf("%010d 00000 n \n", $s);
        }

        return $pdf . "trailer\n<< /Size " . (\count($objekte) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    }

    private function repo(string $entity): EntityRepository
    {
        return $this->registry->getRepository($entity);
    }
}
