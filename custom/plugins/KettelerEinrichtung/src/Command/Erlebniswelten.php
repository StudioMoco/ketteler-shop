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
        foreach ($daten['seiten'] as $seite) {
            $id = self::id('cms.' . $seite['schluessel']);
            $kategorie = self::id('kategorie.' . $seite['kategorie']);
            $vorhanden = $this->repo('cms_page')->searchIds(new Criteria([$id]), $context)->firstId() !== null;
            if ($vorhanden && !$neu) {
                $io->writeln(\sprintf('Erlebniswelt „%s“ besteht schon und bleibt, wie sie in der Verwaltung gepflegt ist.', $seite['name']));
                $this->zuweisen($kategorie, $id, $context, false);
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
            $this->zuweisen($kategorie, $id, $context, true);
            $io->writeln(\sprintf('Erlebniswelt „%s“ angelegt (%d Blöcke).', $seite['name'], \count($seite['bloecke'])));
        }
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

    private function repo(string $entity): EntityRepository
    {
        return $this->registry->getRepository($entity);
    }
}
