<?php declare(strict_types=1);

namespace Ketteler\Theme\Cms;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Ein Resolver für alle Ketteler-Elemente der Erlebniswelten (Typ je Dienst). Sammelt aus der Konfiguration alle
 * gewählten Produkte, Kategorien und Medien (nach Feldnamen, auch in Listen) und lädt sie in einem Zug: Produkte über
 * den Verkaufskanal (berechnete Preise, Cover), Kategorien, Medien, dazu je Kategorie die ersten drei Produkte (für
 * den Cover-Stapel, wenn keine Bilder gewählt sind). Das Ergebnis steht im Template unter element.data.
 */
class KettelerElementResolver extends AbstractCmsElementResolver
{
    /** Feldnamen, deren Wert eine Produkt-, Kategorie- oder Medien-ID (bzw. eine Liste davon) ist */
    public const PRODUKT_FELDER = ['produkt', 'produkte'];
    public const KATEGORIE_FELDER = ['kategorie'];
    public const MEDIEN_FELDER = ['bild', 'bilder', 'video', 'videoPoster', 'videoUntertitel', 'datei', 'knopfDatei', 'foto', 'kastenFussDatei'];
    /** Kategorie, deren Produkte vollständig geladen und nach Jahrgang gruppiert werden (Einzelhefte) */
    public const HEFT_FELD = 'heftKategorie';

    public function __construct(private readonly string $typ)
    {
    }

    public function getType(): string
    {
        return $this->typ;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $ids = self::ids($this->werte($slot));
        $sammlung = new CriteriaCollection();
        if ($ids['produkte'] !== []) {
            $c = new Criteria($ids['produkte']);
            $c->addAssociation('cover.media');
            $sammlung->add($this->schluessel($slot, 'produkte'), SalesChannelProductDefinition::class, $c);
        }
        if ($ids['kategorien'] !== []) {
            $sammlung->add($this->schluessel($slot, 'kategorien'), CategoryDefinition::class, new Criteria($ids['kategorien']));
            foreach ($ids['kategorien'] as $kategorie) {
                $c = (new Criteria())->addFilter(new EqualsFilter('categoriesRo.id', $kategorie))
                    ->addAssociation('cover.media')->addSorting(new FieldSorting('name'))->setLimit(3);
                $sammlung->add($this->schluessel($slot, 'kategorie-' . $kategorie), SalesChannelProductDefinition::class, $c);
            }
        }
        if ($ids['medien'] !== []) {
            $sammlung->add($this->schluessel($slot, 'medien'), MediaDefinition::class, new Criteria($ids['medien']));
        }
        $hefte = $this->werte($slot)[self::HEFT_FELD] ?? null;
        if (\is_string($hefte) && Uuid::isValid($hefte)) {
            $c = (new Criteria())->addFilter(new EqualsFilter('categoriesRo.id', $hefte))->addAssociation('cover.media')->setLimit(500);
            $sammlung->add($this->schluessel($slot, 'hefte'), SalesChannelProductDefinition::class, $c);
        }

        return $sammlung->all() === [] ? null : $sammlung;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $ids = self::ids($this->werte($slot));
        $daten = ['produkte' => [], 'kategorien' => [], 'medien' => [], 'kategorieProdukte' => []];
        foreach ($result->get($this->schluessel($slot, 'produkte'))?->getEntities() ?? [] as $p) {
            $daten['produkte'][$p->getId()] = $p;
        }
        foreach ($result->get($this->schluessel($slot, 'kategorien'))?->getEntities() ?? [] as $k) {
            $daten['kategorien'][$k->getId()] = $k;
        }
        foreach ($ids['kategorien'] as $kategorie) {
            $daten['kategorieProdukte'][$kategorie] = array_values($result->get($this->schluessel($slot, 'kategorie-' . $kategorie))?->getEntities()?->getElements() ?? []);
        }
        foreach ($result->get($this->schluessel($slot, 'medien'))?->getEntities() ?? [] as $m) {
            $daten['medien'][$m->getId()] = $m;
        }
        $daten['jahrgaenge'] = self::nachJahrgang(array_values($result->get($this->schluessel($slot, 'hefte'))?->getEntities()?->getElements() ?? []));
        $slot->setData(new ArrayStruct($daten));
    }

    /**
     * Einzelhefte nach Jahrgang (neuester zuerst), im Jahrgang nach Heftnummer absteigend. Jahrgang und Heft kommen aus
     * den Zusatzfeldern ketteler_jahrgang und ketteler_heft, sonst aus der Artikelnummer (Z5-2026, auch TEST-P-ZMV-2026-5),
     * sonst aus dem Namen („5/2026“). Hefte ohne erkennbaren Jahrgang (Abo, Probeheft) fehlen hier.
     *
     * @param list<object> $produkte
     *
     * @return array<int, list<object>>
     */
    public static function nachJahrgang(array $produkte): array
    {
        $gruppen = [];
        $nummern = [];
        foreach ($produkte as $p) {
            $felder = method_exists($p, 'getCustomFields') ? ($p->getCustomFields() ?? []) : [];
            [$jahr, $heft] = self::heft((string) $p->getProductNumber(), (string) ($p->getTranslation('name') ?? $p->getName() ?? ''));
            $jahr = (int) ($felder['ketteler_jahrgang'] ?? 0) ?: $jahr;
            $heft = (int) ($felder['ketteler_heft'] ?? 0) ?: $heft;
            if ($jahr === null) {
                continue;
            }
            $gruppen[$jahr][] = $p;
            $nummern[$p->getId()] = $heft ?? 0;
        }
        krsort($gruppen);
        foreach ($gruppen as &$liste) {
            usort($liste, static fn (object $a, object $b): int => $nummern[$b->getId()] <=> $nummern[$a->getId()]);
        }

        return $gruppen;
    }

    /**
     * Jahrgang und Heft aus Artikelnummer bzw. Name. Der Jahrgang ist die vierstellige Zahl 19xx/20xx, das Heft die
     * ein- bis zweistellige Zahl daneben (Z5-2026, ZMV-2026-5, „ZMV-Einzelheft 5/2026“).
     *
     * @return array{0: ?int, 1: ?int}
     */
    public static function heft(string $nummer, string $name = ''): array
    {
        foreach ([$nummer, $name] as $text) {
            preg_match_all('/\d+/', $text, $t);
            $zahlen = $t[0];
            foreach ($zahlen as $i => $z) {
                if (preg_match('/^(19|20)\d\d$/', $z)) {
                    $heft = null;
                    foreach ([$i - 1, $i + 1] as $n) {
                        if (isset($zahlen[$n]) && \strlen($zahlen[$n]) <= 2) {
                            $heft = (int) $zahlen[$n];
                            break;
                        }
                    }

                    return [(int) $z, $heft];
                }
            }
        }

        return [null, null];
    }

    /**
     * IDs aus der Konfiguration, nach Feldnamen, auch in Listen von Einträgen.
     *
     * @param array<string, mixed> $werte
     *
     * @return array{produkte: list<string>, kategorien: list<string>, medien: list<string>}
     */
    public static function ids(array $werte): array
    {
        $ids = ['produkte' => [], 'kategorien' => [], 'medien' => []];
        $sammeln = static function (array $daten) use (&$sammeln, &$ids): void {
            foreach ($daten as $feld => $wert) {
                if (\is_array($wert) && array_is_list($wert) && $wert !== [] && \is_array($wert[0])) {
                    foreach ($wert as $eintrag) {
                        $sammeln($eintrag);
                    }
                    continue;
                }
                $ziel = match (true) {
                    \in_array($feld, self::PRODUKT_FELDER, true) => 'produkte',
                    \in_array($feld, self::KATEGORIE_FELDER, true) => 'kategorien',
                    \in_array($feld, self::MEDIEN_FELDER, true) => 'medien',
                    default => null,
                };
                if ($ziel === null) {
                    continue;
                }
                foreach ((array) $wert as $id) {
                    if (\is_string($id) && Uuid::isValid($id) && !\in_array($id, $ids[$ziel], true)) {
                        $ids[$ziel][] = $id;
                    }
                }
            }
        };
        $sammeln($werte);

        return $ids;
    }

    /** @return array<string, mixed> Werte der Konfiguration ohne die Hülle {source, value} */
    private function werte(CmsSlotEntity $slot): array
    {
        $werte = [];
        foreach ($slot->getFieldConfig()->getElements() as $feld => $konfig) {
            $werte[$feld] = $konfig->getValue();
        }

        return $werte;
    }

    private function schluessel(CmsSlotEntity $slot, string $teil): string
    {
        return 'ketteler_' . $teil . '_' . $slot->getUniqueIdentifier();
    }
}
