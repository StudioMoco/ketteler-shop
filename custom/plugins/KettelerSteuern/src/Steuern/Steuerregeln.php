<?php declare(strict_types=1);

namespace Ketteler\Steuern\Steuern;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Setzt die Länderregeln der drei Steuern (19 %, 7 %, Online) nach dem OSS-Schalter.
 * OSS aus: keine Länderregeln, also deutsche Umsatzsteuer für alle (Bruttopreis gleich).
 * OSS an: Regel je EU-Land (ohne Deutschland) mit dem Satz aus steuersaetze.json.
 * Steuerfreiheit (Schweiz, Großbritannien, Einrichtungen mit bestätigter USt-IdNr.) regeln Land und TaxDetector.
 */
class Steuerregeln
{
    public const KONFIG_OSS = 'KettelerSteuern.config.ossAktiv';
    public const DATEI = __DIR__ . '/../Resources/daten/steuersaetze.json';

    public function __construct(
        private readonly EntityRepository $steuern,
        private readonly EntityRepository $regeln,
        private readonly EntityRepository $laender,
        private readonly Connection $db,
        private readonly SystemConfigService $config,
        private readonly ?CacheClearer $cache = null,
    ) {
    }

    /** @return array{oss: bool, regeln: int} */
    public function anwenden(Context $context): array
    {
        $oss = self::istAn($this->config->get(self::KONFIG_OSS));
        $steuern = $this->steuerIds($context);
        $this->db->executeStatement('DELETE FROM tax_rule WHERE tax_id IN (:t)', ['t' => Uuid::fromHexToBytesList(array_values($steuern))], ['t' => ArrayParameterType::BINARY]);
        if (!$oss) {
            $this->cacheLeeren();

            return ['oss' => false, 'regeln' => 0];
        }

        $saetze = self::saetze();
        $typ = $this->db->fetchOne("SELECT LOWER(HEX(id)) FROM tax_rule_type WHERE technical_name = 'entire_country'");
        $laender = [];
        foreach ($this->laender->search((new Criteria())->addFilter(new EqualsAnyFilter('iso', array_keys($saetze))), $context) as $land) {
            $laender[(string) $land->getIso()] = $land->getId();
        }
        $zeilen = [];
        foreach ($saetze as $iso => $satz) {
            if (!isset($laender[$iso])) {
                continue;
            }
            foreach (['print19' => 'standard', 'print7' => 'buch', 'online' => 'elektronisch'] as $steuer => $art) {
                $zeilen[] = [
                    'id' => Uuid::fromStringToHex('ketteler.steuerregel.' . $steuer . '.' . $iso),
                    'taxId' => $steuern[$steuer],
                    'countryId' => $laender[$iso],
                    'taxRuleTypeId' => $typ,
                    'taxRate' => (float) $satz[$art],
                ];
            }
        }
        $this->regeln->upsert($zeilen, $context);
        $this->cacheLeeren();

        return ['oss' => true, 'regeln' => \count($zeilen)];
    }

    /**
     * Die Steuerregeln stecken im zwischengespeicherten Verkaufskanal-Kontext und in zwischengespeicherten Seiten. Das
     * Löschen oben geht direkt an die Datenbank und löst keine Invalidierung aus: ohne Leeren rechnet der Shop (prod)
     * nach dem Umschalten weiter mit den alten Sätzen. Wie „Caches leeren“ in der Verwaltung; der Schalter ist selten.
     */
    private function cacheLeeren(): void
    {
        $this->cache?->clear();
    }

    /** true, „true“, „1“, „ja“ = an; alles andere (auch der Text „false“) = aus */
    public static function istAn(mixed $wert): bool
    {
        return \is_bool($wert) ? $wert : \in_array(strtolower(trim((string) $wert)), ['1', 'true', 'ja', 'yes', 'on'], true);
    }

    /** @return array<string, array{standard: float|int, buch: float|int, elektronisch: float|int}> */
    public static function saetze(): array
    {
        return json_decode((string) file_get_contents(self::DATEI), true, 512, \JSON_THROW_ON_ERROR)['laender'];
    }

    /** @return array{print19: string, print7: string, online: string} */
    private function steuerIds(Context $context): array
    {
        $online = Uuid::fromStringToHex('ketteler.steuer.online');
        $nachSatz = function (float $satz) use ($online, $context): string {
            $id = $this->steuern->searchIds((new Criteria())
                ->addFilter(new EqualsFilter('taxRate', $satz))
                ->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('id', $online)]))
                ->setLimit(1), $context)->firstId();
            if ($id === null) {
                throw new \RuntimeException(\sprintf('Steuer %s %% fehlt (ketteler:einrichten zuerst).', $satz));
            }

            return $id;
        };

        return ['print19' => $nachSatz(19.0), 'print7' => $nachSatz(7.0), 'online' => $online];
    }
}
