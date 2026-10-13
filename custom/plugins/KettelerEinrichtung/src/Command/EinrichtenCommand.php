<?php declare(strict_types=1);

namespace Ketteler\Einrichtung\Command;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Grundaufbau des Ketteler-Shops. Idempotent: alle Datensätze haben feste IDs (aus festen Schlüsseln abgeleitet),
 * jeder Lauf schreibt denselben Stand. Daten: src/Resources/daten/*.json.
 */
#[AsCommand(name: 'ketteler:einrichten', description: 'Grundeinstellungen, Zusatzfelder, Kategorien und Testartikel anlegen')]
class EinrichtenCommand extends Command
{
    private const DATEN = __DIR__ . '/../Resources/daten/';

    private Context $context;

    private SymfonyStyle $io;

    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly Connection $db,
        private readonly SystemConfigService $config,
        private readonly Erlebniswelten $erlebniswelten,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('erlebniswelten-neu', null, InputOption::VALUE_NONE, 'Startseite und Sortiment neu anlegen (überschreibt die Pflege in der Verwaltung)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->context = Context::createCLIContext();

        $salesChannelId = $this->storefrontId();
        $laender = $this->laender();
        $steuern = $this->steuern($laender);
        $this->zusatzfelder();
        [$regelVersand, $regelOnline] = $this->regeln();
        $zonen = $this->zonenRegeln($laender);
        [$lieferzeitPost, $lieferzeitSofort] = $this->lieferzeiten();
        [$versandPost, $versandOnline] = $this->versandarten($regelVersand, $regelOnline, $zonen, $lieferzeitPost, $lieferzeitSofort);
        $zahlarten = $this->zahlarten();
        [$wurzel, $fuss] = $this->kategorien();
        $this->rechtstexte();
        $this->widerrufsfunktion();
        $this->verkaufskanal($salesChannelId, $laender, $zahlarten, $versandPost, [$versandPost, $versandOnline], $wurzel, $fuss);
        $this->nummernkreise();
        $this->artikel($salesChannelId, $steuern, $lieferzeitPost, $lieferzeitSofort);
        $this->erlebniswelten->anlegen($this->context, $this->io, (bool) $input->getOption('erlebniswelten-neu'));
        $this->einstellungen();
        $this->mails($salesChannelId);

        $this->io->success('Grundaufbau fertig.');

        return self::SUCCESS;
    }

    private function id(string $schluessel): string
    {
        return Uuid::fromStringToHex('ketteler.' . $schluessel);
    }

    private function repo(string $entity): EntityRepository
    {
        return $this->registry->getRepository($entity);
    }

    private function ersteId(string $entity, Criteria $criteria): ?string
    {
        return $this->repo($entity)->searchIds($criteria->setLimit(1), $this->context)->firstId();
    }

    /** @return array<string, mixed> */
    private function json(string $datei): array
    {
        return json_decode((string) file_get_contents(self::DATEN . $datei), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function storefrontId(): string
    {
        $id = $this->ersteId('sales_channel', (new Criteria())->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT)));
        if ($id === null) {
            throw new \RuntimeException('Kein Storefront-Verkaufskanal gefunden (system:install mit --basic-setup ausführen).');
        }

        return $id;
    }

    /** @return array<string, string> ISO-Code => ID; Lieferländer und Steuerfreiheit laut laender.json */
    private function laender(): array
    {
        $daten = $this->json('laender.json');
        $iso = array_merge(...array_map(static fn (array $z): array => $z['laender'], array_values($daten['zonen'])));
        $ids = [];
        foreach ($this->repo('country')->search((new Criteria())->addFilter(new EqualsAnyFilter('iso', $iso)), $this->context) as $land) {
            $ids[(string) $land->getIso()] = $land->getId();
        }
        $fehlt = array_diff($iso, array_keys($ids));
        if ($fehlt !== []) {
            throw new \RuntimeException('Länder fehlen: ' . implode(', ', $fehlt));
        }
        $steuerfrei = static fn (bool $an): array => ['enabled' => $an, 'currencyId' => Defaults::CURRENCY, 'amount' => 0];
        $zeilen = [];
        foreach ($ids as $code => $id) {
            $zeilen[] = [
                'id' => $id,
                'active' => true,
                'shippingAvailable' => true,
                'position' => $code === 'DE' ? 1 : 10,
                'customerTax' => $steuerfrei(\in_array($code, $daten['steuerfrei_alle'], true)),
                'companyTax' => $steuerfrei(\in_array($code, $daten['steuerfrei_firmen_mit_ustid'], true) || \in_array($code, $daten['steuerfrei_alle'], true)),
                // Formatprüfung von Shopware; die Bestätigung über VIES macht KettelerSteuern
                'checkVatIdPattern' => $code !== 'CH' && $code !== 'GB',
            ];
        }
        $this->repo('country')->update($zeilen, $this->context);
        $this->io->writeln(\sprintf('%d Lieferländer eingerichtet (CH und GB ohne Umsatzsteuer, EU-Firmen mit USt-IdNr. steuerfrei).', \count($ids)));

        return $ids;
    }

    /**
     * Steuern laut steuern.json. Die beiden Druck-Steuern sind die Shopware-Vorgaben (19 % und 7 %), umbenannt.
     * „Online“ ist eine eigene Steuer. Die Länderregeln (OSS-Schalter, Sätze je Land) verwaltet KettelerSteuern.
     *
     * @param array<string, string> $laender
     *
     * @return array<string, string> Schlüssel (print19, print7, online) => ID
     */
    private function steuern(array $laender): array
    {
        $daten = $this->json('steuern.json');
        $onlineId = $this->id('steuer.online');
        $ergebnis = [];
        foreach ($daten['steuern'] as $schluessel => $steuer) {
            $id = $schluessel === 'online' ? $onlineId : ($this->ersteId('tax', (new Criteria())
                ->addFilter(new EqualsFilter('taxRate', (float) $steuer['satz']))
                ->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('id', $onlineId)]))) ?? $this->id('steuer.' . $schluessel));
            $this->repo('tax')->upsert([['id' => $id, 'taxRate' => (float) $steuer['satz'], 'name' => $steuer['name'], 'position' => \count($ergebnis) + 1]], $this->context);
            $ergebnis[$schluessel] = $id;
        }

        // Länderregeln (OSS) setzt das Plugin KettelerSteuern (bin/console ketteler:steuern:anwenden)
        $this->io->writeln('Steuern 19 %, 7 % und „Online“ angelegt (Länderregeln: KettelerSteuern).');

        return $ergebnis;
    }

    private function zusatzfelder(): void
    {
        $auswahl = static fn (array $werte): array => array_map(
            static fn (string $wert, string $label): array => ['value' => $wert, 'label' => ['de-DE' => $label, 'en-GB' => $label]],
            array_keys($werte),
            $werte,
        );

        $this->repo('custom_field_set')->upsert([[
            'id' => $this->id('zusatzfelder.artikel'),
            'name' => 'ketteler_artikel',
            'config' => ['label' => ['de-DE' => 'Ketteler', 'en-GB' => 'Ketteler'], 'translated' => true],
            'relations' => [['id' => $this->id('zusatzfelder.artikel.product'), 'entityName' => 'product']],
            'customFields' => [
                [
                    'id' => $this->id('zusatzfeld.artikelart'),
                    'name' => 'ketteler_artikelart',
                    'type' => CustomFieldTypes::SELECT,
                    'allowCartExpose' => true,
                    'config' => [
                        'label' => ['de-DE' => 'Artikelart', 'en-GB' => 'Article type'],
                        'helpText' => ['de-DE' => 'Print wird verschickt, Online nicht, Bundle enthält beides.'],
                        'componentName' => 'sw-single-select',
                        'customFieldType' => 'select',
                        'customFieldPosition' => 1,
                        'options' => $auswahl(['print' => 'Print', 'online' => 'Online', 'bundle' => 'Bundle']),
                    ],
                ],
                [
                    'id' => $this->id('zusatzfeld.produkt'),
                    'name' => 'ketteler_produkt',
                    'type' => CustomFieldTypes::SELECT,
                    'allowCartExpose' => true,
                    'config' => [
                        'label' => ['de-DE' => 'Online-Werk (Lizenz)', 'en-GB' => 'Online work (licence)'],
                        'helpText' => ['de-DE' => 'Für Online und Bundle: welche Lizenz das Lizenz-Plugin nach dem Kauf vergibt. Leer bei Print.'],
                        'componentName' => 'sw-single-select',
                        'customFieldType' => 'select',
                        'customFieldPosition' => 2,
                        'options' => $auswahl(['avr' => 'AVR (Kompendium AVR Caritas)', 'eichstaetter' => 'Eichstätter Kommentar']),
                    ],
                ],
                [
                    'id' => $this->id('zusatzfeld.abo'),
                    'name' => 'ketteler_abo',
                    'type' => CustomFieldTypes::TEXT,
                    'allowCartExpose' => true,
                    'config' => [
                        'label' => ['de-DE' => 'Abonnement (Hinweis für Kunden)', 'en-GB' => 'Subscription note'],
                        'helpText' => ['de-DE' => 'Leer = kein Abo. Sonst kurzer Hinweis zu Laufzeit und Verlängerung; der Artikel wird im Shop als Abo gekennzeichnet. Den Vertrag legt die Verwaltung in weclapp an.'],
                        'componentName' => 'sw-field',
                        'customFieldType' => 'text',
                        'customFieldPosition' => 3,
                    ],
                ],
                [
                    'id' => $this->id('zusatzfeld.autor'),
                    'name' => 'ketteler_autor',
                    'type' => CustomFieldTypes::TEXT,
                    'config' => [
                        'label' => ['de-DE' => 'Autor/Herausgeber', 'en-GB' => 'Author/editor'],
                        'helpText' => ['de-DE' => 'Wird in Produktreihen über dem Titel gezeigt, z. B. „Oxenknecht-Witzsch (Hg.)“. Vorläufig, bis feststeht, welches Feld weclapp liefert.'],
                        'componentName' => 'sw-field',
                        'customFieldType' => 'text',
                        'customFieldPosition' => 4,
                    ],
                ],
            ],
        ]], $this->context);
        // allowCartExpose: Shopware gibt die Felder nur dann an Warenkorb und Bestellpositionen weiter
        $this->io->writeln('Zusatzfelder ketteler_artikelart, ketteler_produkt, ketteler_abo und ketteler_autor angelegt.');
    }

    /** @return array{0: string, 1: string} */
    private function regeln(): array
    {
        $versand = $this->id('regel.versandartikel');
        $online = $this->id('regel.nur-online');
        // „Alle Positionen versandkostenfrei“ (Online-Zugänge); die Versand-Regel ist die Verneinung davon
        $alleVersandfrei = fn (string $k): array => [
            'id' => $this->id($k . '.alle'),
            'type' => 'allLineItemsContainer',
            'position' => 0,
            'children' => [[
                'id' => $this->id($k . '.bedingung'),
                'type' => 'cartHasDeliveryFreeItem',
                'position' => 0,
                'value' => ['allowed' => true],
            ]],
        ];
        $regel = fn (string $k, array $innen): array => [[
            'id' => $this->id($k . '.oder'),
            'type' => 'orContainer',
            'position' => 0,
            'children' => [[
                'id' => $this->id($k . '.und'),
                'type' => 'andContainer',
                'position' => 0,
                'children' => [$innen],
            ]],
        ]];

        $this->repo('rule')->upsert([
            [
                'id' => $versand,
                'name' => 'Warenkorb enthält Artikel mit Versand',
                'description' => 'Mindestens eine Position ist nicht versandkostenfrei (Print oder Bundle).',
                'priority' => 100,
                'conditions' => $regel('regel.versandartikel', [
                    'id' => $this->id('regel.versandartikel.nicht'),
                    'type' => 'notContainer',
                    'position' => 0,
                    'children' => [$alleVersandfrei('regel.versandartikel')],
                ]),
            ],
            [
                'id' => $online,
                'name' => 'Warenkorb enthält nur Online-Artikel',
                'description' => 'Alle Positionen sind versandkostenfrei (Online-Zugänge).',
                'priority' => 100,
                'conditions' => $regel('regel.nur-online', $alleVersandfrei('regel.nur-online')),
            ],
        ], $this->context);
        $this->io->writeln('Regeln für Versand und Online angelegt.');

        return [$versand, $online];
    }

    /**
     * Eine Regel je Versandzone (Lieferland), für die Gewichtsstaffeln.
     *
     * @param array<string, string> $laender
     *
     * @return array<string, string> Zone => Regel-ID
     */
    private function zonenRegeln(array $laender): array
    {
        $zonen = [];
        $zeilen = [];
        foreach ($this->json('laender.json')['zonen'] as $zone => $daten) {
            $id = $this->id('regel.zone.' . $zone);
            $zonen[$zone] = $id;
            $zeilen[] = [
                'id' => $id,
                'name' => 'Lieferland: ' . $daten['name'],
                'description' => 'Versandzone für die Gewichtsstaffeln (KettelerEinrichtung).',
                'priority' => 50,
                'conditions' => [[
                    'id' => $this->id('regel.zone.' . $zone . '.oder'),
                    'type' => 'orContainer',
                    'position' => 0,
                    'children' => [[
                        'id' => $this->id('regel.zone.' . $zone . '.und'),
                        'type' => 'andContainer',
                        'position' => 0,
                        'children' => [[
                            'id' => $this->id('regel.zone.' . $zone . '.land'),
                            'type' => 'customerShippingCountry',
                            'position' => 0,
                            'value' => ['operator' => '=', 'countryIds' => array_values(array_map(static fn (string $c): string => $laender[$c], $daten['laender']))],
                        ]],
                    ]],
                ]],
            ];
        }
        $this->repo('rule')->upsert($zeilen, $this->context);

        return $zonen;
    }

    /** @return array{0: string, 1: string} */
    private function lieferzeiten(): array
    {
        $post = $this->id('lieferzeit.post');
        $sofort = $this->id('lieferzeit.sofort');
        $this->repo('delivery_time')->upsert([
            ['id' => $post, 'name' => '2 bis 4 Werktage', 'min' => 2, 'max' => 4, 'unit' => 'day'],
            ['id' => $sofort, 'name' => 'sofort nach dem Kauf', 'min' => 0, 'max' => 0, 'unit' => 'day'],
        ], $this->context);

        return [$post, $sofort];
    }

    /** @return array{0: string, 1: string} */
    /** @param array<string, string> $zonen Zone => Regel-ID */
    private function versandarten(string $regelVersand, string $regelOnline, array $zonen, string $lieferzeitPost, string $lieferzeitSofort): array
    {
        $post = $this->id('versand.post');
        $online = $this->id('versand.online');
        $preis = static fn (float $brutto): array => [[
            'currencyId' => Defaults::CURRENCY,
            'gross' => $brutto,
            'net' => round($brutto / 1.19, 2),
            'linked' => false,
        ]];

        // Vorhandene Standard-Versandarten der Installation abschalten
        $andere = $this->repo('shipping_method')->searchIds(new Criteria(), $this->context)->getIds();
        $aus = array_values(array_filter($andere, static fn (string $id): bool => !\in_array($id, [$post, $online], true)));
        if ($aus !== []) {
            $this->repo('shipping_method')->update(array_map(static fn (string $id): array => ['id' => $id, 'active' => false], $aus), $this->context);
        }

        $this->repo('shipping_method')->upsert([
            [
                'id' => $post,
                'technicalName' => 'ketteler_post',
                'name' => 'Versand per Post',
                'description' => 'Deutsche Post. Versandkosten nach Gewicht und Lieferland (Werte vorläufig, folgen aus weclapp).',
                'active' => true,
                'position' => 1,
                'taxType' => 'auto',
                'availabilityRuleId' => $regelVersand,
                'deliveryTimeId' => $lieferzeitPost,
                'prices' => $this->gewichtsstaffeln($zonen, $preis),
            ],
            [
                'id' => $online,
                'technicalName' => 'ketteler_online',
                'name' => 'Ohne Versand (Online-Zugang)',
                'description' => 'Für Bestellungen nur mit Online-Zugängen. Der Zugang wird nach dem Kauf freigeschaltet.',
                'active' => true,
                'position' => 2,
                'taxType' => 'auto',
                'availabilityRuleId' => $regelOnline,
                'deliveryTimeId' => $lieferzeitSofort,
                'prices' => [[
                    'id' => $this->id('versand.online.preis'),
                    'calculation' => 1,
                    'quantityStart' => 1,
                    'currencyPrice' => $preis(0.0),
                ]],
            ],
        ], $this->context);
        // Pauschalpreis aus dem ersten Grundaufbau entfernen (erst jetzt, sonst hätte die Versandart kurz keinen Preis)
        if ($this->repo('shipping_method_price')->searchIds(new Criteria([$this->id('versand.post.preis')]), $this->context)->getTotal() > 0) {
            $this->repo('shipping_method_price')->delete([['id' => $this->id('versand.post.preis')]], $this->context);
        }
        $this->io->writeln('Versandarten „Versand per Post“ (Gewichtsstaffeln je Zone) und „Ohne Versand (Online-Zugang)“ angelegt.');

        return [$post, $online];
    }

    /**
     * Preise nach Gewicht (Berechnung 3) je Zone; die Zone wird über die Regel des Lieferlands gewählt.
     *
     * @param array<string, string> $zonen
     * @param callable(float): array<int, array<string, mixed>> $preis
     *
     * @return list<array<string, mixed>>
     */
    private function gewichtsstaffeln(array $zonen, callable $preis): array
    {
        $ergebnis = [];
        foreach ($this->json('versand.json')['staffeln'] as $zone => $staffeln) {
            $von = 0.0;
            foreach ($staffeln as $n => $staffel) {
                $ergebnis[] = [
                    'id' => $this->id('versand.post.preis.' . $zone . '.' . $n),
                    'ruleId' => $zonen[$zone],
                    'calculation' => 3,
                    'quantityStart' => $von,
                    'quantityEnd' => $staffel['bis_kg'] !== null ? (float) $staffel['bis_kg'] : null,
                    'currencyPrice' => $preis((float) $staffel['preis']),
                ];
                $von = $staffel['bis_kg'] !== null ? (float) $staffel['bis_kg'] + 0.001 : $von;
            }
        }

        return $ergebnis;
    }

    /**
     * Rechnung und PayPal (offizielles Plugin SwagPayPal, nur die Zahlart „PayPal“). Alle anderen Zahlarten aus.
     * PayPal läuft lokal im Testmodus (Sandbox); Zugangsdaten nur aus der .env (PAYPAL_SANDBOX_*). Ohne sie blendet das
     * Plugin PayPal in der Kasse aus.
     *
     * @return list<string> IDs, Rechnung zuerst
     */
    private function zahlarten(): array
    {
        $rechnung = null;
        $paypal = null;
        $updates = [];
        foreach ($this->repo('payment_method')->search(new Criteria(), $this->context) as $zahlart) {
            $handler = (string) $zahlart->getHandlerIdentifier();
            if (str_ends_with($handler, '\\InvoicePayment')) {
                $rechnung = $zahlart->getId();
            } elseif (str_ends_with($handler, '\\PayPalPaymentHandler')) {
                $paypal = $zahlart->getId();
            } else {
                $updates[] = ['id' => $zahlart->getId(), 'active' => false];
            }
        }
        if ($rechnung === null) {
            throw new \RuntimeException('Zahlart Rechnung (InvoicePayment) nicht gefunden.');
        }
        $updates[] = ['id' => $rechnung, 'name' => 'Rechnung', 'description' => 'Zahlung auf Rechnung, Zahlungsziel 10 Tage.', 'active' => true, 'position' => 1];
        if ($paypal !== null) {
            $updates[] = ['id' => $paypal, 'name' => 'PayPal', 'active' => true, 'position' => 2];
            $this->config->set('SwagPayPal.settings.sandbox', true);
            // Keine Express-Knöpfe und keine „Später bezahlen“-Werbung außerhalb der Kasse: beide laden Skripte von PayPal,
            // ohne dass PayPal gewählt ist (Gestaltungsgrundsatz: keine fremden Dienste im Browser). In der Kasse lädt
            // PayPal erst, wenn die Kundin PayPal wählt.
            foreach (['ecsDetailEnabled', 'ecsCartEnabled', 'ecsOffCanvasEnabled', 'ecsLoginEnabled', 'ecsListingEnabled',
                'installmentBannerDetailPageEnabled', 'installmentBannerCartEnabled', 'installmentBannerOffCanvasCartEnabled',
                'installmentBannerLoginPageEnabled', 'installmentBannerFooterEnabled', 'crossBorderMessagingEnabled'] as $feld) {
                $this->config->set('SwagPayPal.settings.' . $feld, false);
            }
            foreach (['PAYPAL_SANDBOX_CLIENT_ID' => 'clientIdSandbox', 'PAYPAL_SANDBOX_CLIENT_SECRET' => 'clientSecretSandbox', 'PAYPAL_SANDBOX_MERCHANT_ID' => 'merchantPayerIdSandbox'] as $env => $feld) {
                $wert = trim((string) ($_SERVER[$env] ?? getenv($env) ?: ''));
                if ($wert !== '') {
                    $this->config->set('SwagPayPal.settings.' . $feld, $wert);
                }
            }
        }
        $this->repo('payment_method')->update($updates, $this->context);
        $this->io->writeln($paypal !== null ? 'Zahlarten „Rechnung“ und „PayPal“ (Testmodus) aktiv, andere aus.' : 'Zahlart „Rechnung“ aktiv (PayPal-Plugin fehlt).');

        return array_values(array_filter([$rechnung, $paypal]));
    }

    /** @return array{0: string, 1: string} IDs von Navigationswurzel und Fußzeile */
    private function kategorien(): array
    {
        $daten = $this->json('kategorien.json');
        $zeilen = [];
        $wurzel = $this->id('kategorie.' . $daten['wurzel']['schluessel']);
        $zeilen[] = ['id' => $wurzel, 'parentId' => null, 'afterCategoryId' => null, 'name' => $daten['wurzel']['name'], 'type' => 'page', 'active' => true, 'visible' => true];
        $this->kategorieKinder($daten['navigation'], $wurzel, $zeilen);

        $fuss = $this->id('kategorie.' . $daten['fuss']['schluessel']);
        $zeilen[] = ['id' => $fuss, 'parentId' => null, 'afterCategoryId' => $wurzel, 'name' => $daten['fuss']['name'], 'type' => 'folder', 'active' => true, 'visible' => true];
        $this->kategorieKinder($daten['fuss']['kinder'], $fuss, $zeilen, true);

        // Eltern vor Kindern schreiben (afterCategoryId zeigt immer auf ein schon geschriebenes Geschwister)
        $this->repo('category')->upsert($zeilen, $this->context);
        // Nicht mehr vorgesehene Kategorien (z. B. alte Fußspalte „Service“) entfernen, sofern leer
        $weg = array_map(fn (string $k): array => ['id' => $this->id('kategorie.' . $k)], $daten['entfernt'] ?? []);
        $vorhanden = $weg === [] ? [] : $this->repo('category')->searchIds(new Criteria(array_column($weg, 'id')), $this->context)->getIds();
        if ($vorhanden !== []) {
            $this->repo('category')->delete(array_map(static fn (string $id): array => ['id' => $id], $vorhanden), $this->context);
        }
        $this->io->writeln(\sprintf('%d Kategorien angelegt (%d alte entfernt).', \count($zeilen), \count($vorhanden)));

        return [$wurzel, $fuss];
    }

    /**
     * @param list<array<string, mixed>> $kinder
     * @param list<array<string, mixed>> $zeilen
     */
    private function kategorieKinder(array $kinder, string $elternId, array &$zeilen, bool $fuss = false): void
    {
        $vorher = null;
        foreach ($kinder as $kind) {
            $id = $this->id('kategorie.' . $kind['schluessel']);
            $hatKinder = !empty($kind['kinder']);
            $zeile = [
                'id' => $id,
                'parentId' => $elternId,
                'afterCategoryId' => $vorher,
                'name' => $kind['name'],
                'type' => $fuss && $hatKinder ? 'folder' : 'page',
                'active' => true,
                'visible' => empty($kind['versteckt']),
                'description' => !empty($kind['platzhalter']) || ($fuss && !$hatKinder && empty($kind['link'])) ? '<p>Platzhalter. Der Text folgt.</p>' : null,
            ];
            // Verweis statt eigener Seite: auf eine andere Kategorie oder eine Adresse (z. B. /mehrplatz-anfrage)
            if (isset($kind['link']['kategorie'])) {
                $zeile += ['linkType' => 'category', 'internalLink' => $this->id('kategorie.' . $kind['link']['kategorie']), 'linkNewTab' => false];
                $zeile['type'] = 'link';
            } elseif (isset($kind['link']['adresse'])) {
                $zeile += ['linkType' => 'external', 'externalLink' => $kind['link']['adresse'], 'linkNewTab' => false];
                $zeile['type'] = 'link';
            }
            $zeilen[] = $zeile;
            if ($hatKinder) {
                $this->kategorieKinder($kind['kinder'], $id, $zeilen, $fuss);
            }
            $vorher = $id;
        }
    }

    /**
     * EU-Widerrufsfunktion (Shopware ab 6.6.10.14): Knopf im Fuß einschalten, Empfänger aus der .env
     * (KETTELER_WIDERRUF_EMAIL) in das Formular eintragen, Bestätigungstext setzen, Seite mit der Fußzeile verknüpfen.
     * Texte von Ketteler zu prüfen.
     */
    private function widerrufsfunktion(): void
    {
        // Die mitgelieferte Seite von Shopware ist gesperrt (nicht änderbar). Darum eine eigene Seite mit demselben
        // Formular (Typ revocationRequest), eingetragen als Widerrufsseite in den Shop-Einstellungen.
        $empfaenger = trim((string) ($_SERVER['KETTELER_WIDERRUF_EMAIL'] ?? getenv('KETTELER_WIDERRUF_EMAIL') ?: ''));
        $seite = $this->id('cms.widerrufsfunktion');
        $this->repo('cms_page')->upsert([[
            'id' => $seite,
            'type' => 'page',
            'name' => 'Widerruf erklären (EU-Widerrufsfunktion, Texte von Ketteler zu prüfen)',
            'sections' => [[
                'id' => $this->id('cms.widerrufsfunktion.bereich'),
                'type' => 'default',
                'position' => 0,
                'sizingMode' => 'boxed',
                'blocks' => [
                    [
                        'id' => $this->id('cms.widerrufsfunktion.text'),
                        'type' => 'text',
                        'position' => 0,
                        'sectionPosition' => 'main',
                        'slots' => [[
                            'id' => $this->id('cms.widerrufsfunktion.text.inhalt'),
                            'type' => 'text',
                            'slot' => 'content',
                            'config' => ['content' => ['source' => 'static', 'value' => '<h1>Widerruf erklären</h1>'
                                . '<p>Hier können Sie den Widerruf eines Vertrags erklären, den Sie in unserem Shop geschlossen haben. '
                                . 'Bitte geben Sie die Bestellnummer an, sie steht in Ihrer Bestellbestätigung. Nach dem Absenden '
                                . 'erhalten Sie eine Bestätigung per E-Mail.</p>']],
                        ]],
                    ],
                    [
                        'id' => $this->id('cms.widerrufsfunktion.formular'),
                        'type' => 'form',
                        'position' => 1,
                        'sectionPosition' => 'main',
                        'slots' => [[
                            'id' => $this->id('cms.widerrufsfunktion.formular.feld'),
                            'type' => 'form',
                            'slot' => 'content',
                            'config' => [
                                'type' => ['source' => 'static', 'value' => 'revocationRequest'],
                                'title' => ['source' => 'static', 'value' => ''],
                                'mailReceiver' => ['source' => 'static', 'value' => $empfaenger !== '' ? [$empfaenger] : []],
                                'confirmationText' => ['source' => 'static', 'value' => 'Vielen Dank. Ihr Widerruf ist bei uns eingegangen. Eine Bestätigung haben wir Ihnen per E-Mail geschickt.'],
                            ],
                        ]],
                    ],
                ],
            ]],
        ]], $this->context);
        $this->config->set('core.basicInformation.revocationRequestPage', $seite);
        $this->config->set('core.basicInformation.showRevocationButton', true);
        $this->repo('category')->update([['id' => $this->id('kategorie.fuss-widerruf-erklaeren'), 'cmsPageId' => $seite, 'description' => null]], $this->context);
        $this->io->writeln(\sprintf('Widerrufsfunktion eingeschaltet (Empfänger: %s).', $empfaenger !== '' ? 'aus KETTELER_WIDERRUF_EMAIL' : 'Shop-E-Mail, KETTELER_WIDERRUF_EMAIL fehlt'));
    }

    /**
     * Rechtstexte aus dem bisherigen Shop (Entwürfe, von Ketteler zu prüfen) als Erlebniswelt-Seiten, verknüpft mit den
     * Fußzeilen-Kategorien und den Shop-Einstellungen (AGB-Link in der Kasse, Widerruf, Datenschutz, Impressum).
     * Quelle: content/rechtstexte/*.md, umgewandelt mit tools/rechtstexte_html.py nach daten/rechtstexte/*.html.
     */
    private function rechtstexte(): void
    {
        $texte = [
            'impressum' => ['Impressum', 'fuss-impressum', 'core.basicInformation.imprintPage'],
            'agb' => ['Allgemeine Geschäftsbedingungen', 'fuss-agb', 'core.basicInformation.tosPage'],
            'widerruf' => ['Widerrufsbelehrung', 'fuss-widerruf', 'core.basicInformation.revocationPage'],
            'datenschutz' => ['Datenschutzerklärung', 'fuss-datenschutz', 'core.basicInformation.privacyPage'],
            'versand-lieferung' => ['Versand und Lieferung', 'fuss-versand', 'core.basicInformation.shippingPaymentInfoPage'],
        ];
        $seiten = [];
        $kategorien = [];
        foreach ($texte as $datei => [$titel, $kategorie, $einstellung]) {
            $pfad = self::DATEN . 'rechtstexte/' . $datei . '.html';
            if (!is_file($pfad)) {
                $this->io->warning('Rechtstext fehlt: ' . $datei . ' (python3 tools/rechtstexte_html.py)');
                continue;
            }
            $id = $this->id('cms.rechtstext.' . $datei);
            $seiten[] = [
                'id' => $id,
                'type' => 'page',
                'name' => 'Rechtstext: ' . $titel . ' (Entwurf, von Ketteler zu prüfen)',
                'sections' => [[
                    'id' => $this->id('cms.rechtstext.' . $datei . '.bereich'),
                    'type' => 'default',
                    'position' => 0,
                    'sizingMode' => 'boxed',
                    'blocks' => [
                        [
                            'id' => $this->id('cms.rechtstext.' . $datei . '.titel'),
                            'type' => 'text',
                            'position' => 0,
                            'sectionPosition' => 'main',
                            'slots' => [[
                                'id' => $this->id('cms.rechtstext.' . $datei . '.titel.text'),
                                'type' => 'text',
                                'slot' => 'content',
                                'config' => ['content' => ['source' => 'static', 'value' => '<h1>' . htmlspecialchars($titel) . '</h1>']],
                            ]],
                        ],
                        [
                            'id' => $this->id('cms.rechtstext.' . $datei . '.inhalt'),
                            'type' => 'text',
                            'position' => 1,
                            'sectionPosition' => 'main',
                            'slots' => [[
                                'id' => $this->id('cms.rechtstext.' . $datei . '.inhalt.text'),
                                'type' => 'text',
                                'slot' => 'content',
                                'config' => ['content' => ['source' => 'static', 'value' => (string) file_get_contents($pfad)]],
                            ]],
                        ],
                    ],
                ]],
            ];
            $kategorien[] = ['id' => $this->id('kategorie.' . $kategorie), 'cmsPageId' => $id, 'description' => null];
            $this->config->set($einstellung, $id);
        }
        $this->repo('cms_page')->upsert($seiten, $this->context);
        $this->repo('category')->update($kategorien, $this->context);
        $this->io->writeln(\sprintf('%d Rechtstexte als Seiten angelegt (Entwürfe, von Ketteler zu prüfen).', \count($seiten)));
    }

    /**
     * @param array<string, string> $laender
     * @param list<string> $zahlarten
     * @param list<string> $versandarten
     */
    private function verkaufskanal(string $id, array $laender, array $zahlarten, string $versand, array $versandarten, string $wurzel, string $fuss): void
    {
        $landId = $laender['DE'];
        $zahlart = $zahlarten[0];
        $this->repo('sales_channel')->update([[
            'id' => $id,
            'name' => 'KETTELER-Verlag',
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'countryId' => $landId,
            'paymentMethodId' => $zahlart,
            'shippingMethodId' => $versand,
            'navigationCategoryId' => $wurzel,
            'navigationCategoryDepth' => 3,
            'homeEnabled' => false,
            'footerCategoryId' => $fuss,
            'countries' => array_map(static fn (string $l): array => ['id' => $l], array_values($laender)),
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => array_map(static fn (string $z): array => ['id' => $z], $zahlarten),
            'shippingMethods' => array_map(static fn (string $v): array => ['id' => $v], $versandarten),
        ]], $this->context);

        // Zuordnungen, die die Installation zusätzlich angelegt hat, entfernen: nur die Lieferländer, Euro, Deutsch
        $kanal = Uuid::fromHexToBytes($id);
        $this->db->executeStatement(
            'DELETE FROM sales_channel_country WHERE sales_channel_id = :k AND country_id NOT IN (:l)',
            ['k' => $kanal, 'l' => Uuid::fromHexToBytesList(array_values($laender))],
            ['l' => ArrayParameterType::BINARY],
        );
        $this->db->executeStatement('DELETE FROM sales_channel_currency WHERE sales_channel_id = ? AND currency_id <> ?', [$kanal, Uuid::fromHexToBytes(Defaults::CURRENCY)]);
        $this->db->executeStatement(
            'DELETE FROM sales_channel_payment_method WHERE sales_channel_id = :k AND payment_method_id NOT IN (:z)',
            ['k' => $kanal, 'z' => Uuid::fromHexToBytesList($zahlarten)],
            ['z' => ArrayParameterType::BINARY],
        );
        $this->db->executeStatement(
            'DELETE FROM sales_channel_shipping_method WHERE sales_channel_id = ? AND shipping_method_id NOT IN (?, ?)',
            [$kanal, Uuid::fromHexToBytes($versandarten[0]), Uuid::fromHexToBytes($versandarten[1])],
        );
        $this->io->writeln(\sprintf('Verkaufskanal: Deutsch, Euro, %d Lieferländer, Rechnung und PayPal, Versand per Post.', \count($laender)));
    }

    private function nummernkreise(): void
    {
        // Eigene Präfixe, damit Shop-Nummern nie mit weclapp-Nummern (4- und 5-stellig) zusammenfallen
        foreach (['order' => 'SW{n}', 'customer' => 'SK{n}'] as $typ => $muster) {
            $id = $this->ersteId('number_range', (new Criteria())
                ->addFilter(new EqualsFilter('type.technicalName', $typ))
                ->addFilter(new EqualsFilter('global', true)));
            if ($id !== null) {
                $this->repo('number_range')->update([['id' => $id, 'pattern' => $muster, 'start' => 100000]], $this->context);
            }
        }
        $this->io->writeln('Nummernkreise: Bestellungen SW100000 ff., Kunden SK100000 ff.');
    }

    /** @param array<string, string> $steuern Schlüssel => Steuer-ID */
    private function artikel(string $salesChannelId, array $steuern, string $lieferzeitPost, string $lieferzeitSofort): void
    {
        $hersteller = $this->id('hersteller.ketteler');
        $this->repo('product_manufacturer')->upsert([['id' => $hersteller, 'name' => 'KETTELER-Verlag']], $this->context);

        $zeilen = [];
        foreach ($this->json('artikel.json')['artikel'] as $a) {
            $id = $this->id('artikel.' . $a['nummer']);
            $online = $a['artikelart'] === 'online';
            $satz = (float) $this->json('steuern.json')['steuern'][$a['steuer']]['satz'];
            $zeilen[] = [
                'id' => $id,
                'productNumber' => $a['nummer'],
                'name' => $a['name'],
                'description' => '<p>' . htmlspecialchars($a['beschreibung']) . '</p>',
                'active' => true,
                'taxId' => $steuern[$a['steuer']],
                'manufacturerId' => $hersteller,
                'price' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => (float) $a['preis'],
                    'net' => round($a['preis'] / (1 + $satz / 100), 2),
                    'linked' => true,
                ]],
                'stock' => (int) ($a['bestand'] ?? 1000),
                'isCloseout' => false,
                // Online-Zugang einzeln; Mehrplatz vergibt die Verwaltung
                'maxPurchase' => $online ? 1 : null,
                'shippingFree' => $online,
                'weight' => (float) $a['gewicht'],
                'deliveryTimeId' => $online ? $lieferzeitSofort : $lieferzeitPost,
                'customFields' => [
                    'ketteler_artikelart' => $a['artikelart'],
                    'ketteler_produkt' => $a['produkt'],
                    'ketteler_abo' => $a['abo'] ?? null,
                    'ketteler_autor' => $a['autor'] ?? null,
                ],
                'visibilities' => [[
                    'id' => $this->id('artikel.' . $a['nummer'] . '.sichtbar'),
                    'salesChannelId' => $salesChannelId,
                    'visibility' => 30,
                ]],
                'categories' => array_map(fn (string $k): array => ['id' => $this->id('kategorie.' . $k)], $a['kategorien']),
            ];
        }
        $this->repo('product')->upsert($zeilen, $this->context);
        $this->io->writeln(\sprintf('%d Testartikel angelegt.', \count($zeilen)));
    }

    /**
     * Mailtexte aus daten/mails/<technischer Name>/ (Betreff, HTML, Text) und Kopf und Fuß aus daten/mails/_kopf_fuss/.
     * Geschrieben wird die Standardsprache (Deutsch).
     */
    private function mails(string $salesChannelId): void
    {
        $ordner = self::DATEN . 'mails/';
        $lesen = static fn (string $datei): string => (string) file_get_contents($ordner . $datei);
        $anzahl = 0;
        foreach (glob($ordner . '*', \GLOB_ONLYDIR) ?: [] as $pfad) {
            $typ = basename($pfad);
            if ($typ === '_kopf_fuss') {
                continue;
            }
            $id = $this->ersteId('mail_template', (new Criteria())
                ->addFilter(new EqualsFilter('mailTemplateType.technicalName', $typ))
                ->addFilter(new EqualsFilter('systemDefault', true)));
            if ($id === null) {
                $this->io->warning('Mailvorlage ' . $typ . ' nicht gefunden.');
                continue;
            }
            $this->repo('mail_template')->update([[
                'id' => $id,
                'senderName' => 'KETTELER-Verlag',
                'subject' => trim($lesen($typ . '/betreff.txt')),
                'contentHtml' => $lesen($typ . '/inhalt.html.twig'),
                'contentPlain' => $lesen($typ . '/inhalt.txt.twig'),
            ]], $this->context);
            ++$anzahl;
        }

        $kopfFuss = $this->ersteId('mail_header_footer', (new Criteria())->addFilter(new EqualsFilter('systemDefault', true)))
            ?? $this->id('mail.kopf-fuss');
        $this->repo('mail_header_footer')->upsert([[
            'id' => $kopfFuss,
            'systemDefault' => true,
            'name' => 'KETTELER-Verlag',
            'description' => 'Kopf und Fuß aller Mails mit Kundenservice und Impressum',
            'headerHtml' => $lesen('_kopf_fuss/kopf.html.twig'),
            'footerHtml' => $lesen('_kopf_fuss/fuss.html.twig'),
            'headerPlain' => $lesen('_kopf_fuss/kopf.txt.twig'),
            'footerPlain' => $lesen('_kopf_fuss/fuss.txt.twig'),
        ]], $this->context);
        $this->repo('sales_channel')->update([['id' => $salesChannelId, 'mailHeaderFooterId' => $kopfFuss]], $this->context);
        $this->io->writeln(\sprintf('%d Mailtexte und Kopf und Fuß der Mails eingespielt.', $anzahl));
    }

    private function einstellungen(): void
    {
        $werte = [
            'core.basicInformation.shopName' => 'KETTELER-Verlag',
            'core.basicInformation.email' => 'shop@ketteler-verlag.test',
            'core.loginRegistration.showAccountTypeSelection' => true,
            'core.cart.showDeliveryTime' => true,
        ];
        // Einrichtungsassistent von Shopware überspringen: den Grundaufbau macht dieser Befehl
        if (!$this->config->get('core.frw.completedAt')) {
            $werte['core.frw.completedAt'] = (new \DateTimeImmutable())->format(\DATE_ATOM);
        }
        foreach ($werte as $schluessel => $wert) {
            $this->config->set($schluessel, $wert);
        }
        // Link „Kontakt“ in Kopf und Fuß: nur vorbelegen, nicht überschreiben (Pflege unter Erweiterungen > Ketteler Theme)
        if (!$this->config->getString('KettelerTheme.config.kontaktKategorie')) {
            $this->config->set('KettelerTheme.config.kontaktKategorie', $this->id('kategorie.fuss-kontakt'));
        }
        $this->io->writeln('Shop-Name und Grundeinstellungen gesetzt.');
    }
}
