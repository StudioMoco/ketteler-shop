<?php declare(strict_types=1);

namespace Ketteler\Einrichtung\Command;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->context = Context::createCLIContext();

        $salesChannelId = $this->storefrontId();
        $landId = $this->landDeutschland();
        $steuern = $this->steuern();
        $this->zusatzfelder();
        [$regelVersand, $regelOnline] = $this->regeln();
        [$lieferzeitPost, $lieferzeitSofort] = $this->lieferzeiten();
        [$versandPost, $versandOnline] = $this->versandarten($regelVersand, $regelOnline, $lieferzeitPost, $lieferzeitSofort);
        $rechnung = $this->zahlartRechnung();
        [$wurzel, $fuss] = $this->kategorien();
        $this->verkaufskanal($salesChannelId, $landId, $rechnung, $versandPost, [$versandPost, $versandOnline], $wurzel, $fuss);
        $this->nummernkreise();
        $this->artikel($salesChannelId, $steuern, $lieferzeitPost, $lieferzeitSofort);
        $this->einstellungen();

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

    private function landDeutschland(): string
    {
        $id = $this->ersteId('country', (new Criteria())->addFilter(new EqualsFilter('iso', 'DE')));
        if ($id === null) {
            throw new \RuntimeException('Land DE fehlt.');
        }
        $this->repo('country')->update([['id' => $id, 'active' => true, 'shippingAvailable' => true, 'position' => 1]], $this->context);

        return $id;
    }

    /** @return array<int, string> Steuersatz in Prozent => ID */
    private function steuern(): array
    {
        $ergebnis = [];
        foreach ([19 => 'Umsatzsteuer 19 %', 7 => 'Umsatzsteuer 7 %'] as $satz => $name) {
            $id = $this->ersteId('tax', (new Criteria())->addFilter(new EqualsFilter('taxRate', (float) $satz)))
                ?? $this->id('steuer.' . $satz);
            $this->repo('tax')->upsert([['id' => $id, 'taxRate' => (float) $satz, 'name' => $name, 'position' => $satz === 19 ? 1 : 2]], $this->context);
            $ergebnis[$satz] = $id;
        }
        $this->io->writeln('Steuersätze 19 % und 7 % angelegt.');

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
                    'config' => [
                        'label' => ['de-DE' => 'Online-Werk (Lizenz)', 'en-GB' => 'Online work (licence)'],
                        'helpText' => ['de-DE' => 'Für Online und Bundle: welche Lizenz das Lizenz-Plugin nach dem Kauf vergibt. Leer bei Print.'],
                        'componentName' => 'sw-single-select',
                        'customFieldType' => 'select',
                        'customFieldPosition' => 2,
                        'options' => $auswahl(['avr' => 'AVR (Kompendium AVR Caritas)', 'eichstaetter' => 'Eichstätter Kommentar']),
                    ],
                ],
            ],
        ]], $this->context);
        $this->io->writeln('Zusatzfelder ketteler_artikelart und ketteler_produkt angelegt.');
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
    private function versandarten(string $regelVersand, string $regelOnline, string $lieferzeitPost, string $lieferzeitSofort): array
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
                'description' => 'Deutsche Post innerhalb Deutschlands. Versandkosten vorläufig pauschal, später nach Gewicht aus weclapp.',
                'active' => true,
                'position' => 1,
                'taxType' => 'auto',
                'availabilityRuleId' => $regelVersand,
                'deliveryTimeId' => $lieferzeitPost,
                'prices' => [[
                    'id' => $this->id('versand.post.preis'),
                    'calculation' => 1,
                    'quantityStart' => 1,
                    'currencyPrice' => $preis(4.50),
                ]],
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
        $this->io->writeln('Versandarten „Versand per Post“ und „Ohne Versand (Online-Zugang)“ angelegt.');

        return [$post, $online];
    }

    private function zahlartRechnung(): string
    {
        $alle = $this->repo('payment_method')->search(new Criteria(), $this->context);
        $rechnung = null;
        $updates = [];
        foreach ($alle as $zahlart) {
            if (str_ends_with((string) $zahlart->getHandlerIdentifier(), '\\InvoicePayment')) {
                $rechnung = $zahlart->getId();
                continue;
            }
            $updates[] = ['id' => $zahlart->getId(), 'active' => false];
        }
        if ($rechnung === null) {
            throw new \RuntimeException('Zahlart Rechnung (InvoicePayment) nicht gefunden.');
        }
        $updates[] = [
            'id' => $rechnung,
            'name' => 'Rechnung',
            'description' => 'Zahlung auf Rechnung, Zahlungsziel 10 Tage.',
            'active' => true,
            'position' => 1,
        ];
        $this->repo('payment_method')->update($updates, $this->context);
        $this->io->writeln('Zahlart „Rechnung“ aktiv, andere Zahlarten aus.');

        return $rechnung;
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
        $this->io->writeln(\sprintf('%d Kategorien angelegt.', \count($zeilen)));

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
            $zeilen[] = [
                'id' => $id,
                'parentId' => $elternId,
                'afterCategoryId' => $vorher,
                'name' => $kind['name'],
                'type' => $fuss && $hatKinder ? 'folder' : 'page',
                'active' => true,
                'visible' => true,
                'description' => $fuss && !$hatKinder ? '<p>Platzhalter. Der Text folgt.</p>' : null,
            ];
            if ($hatKinder) {
                $this->kategorieKinder($kind['kinder'], $id, $zeilen, $fuss);
            }
            $vorher = $id;
        }
    }

    /** @param list<string> $versandarten */
    private function verkaufskanal(string $id, string $landId, string $zahlart, string $versand, array $versandarten, string $wurzel, string $fuss): void
    {
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
            'footerCategoryId' => $fuss,
            'countries' => [['id' => $landId]],
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => $zahlart]],
            'shippingMethods' => array_map(static fn (string $v): array => ['id' => $v], $versandarten),
        ]], $this->context);

        // Zuordnungen, die die Installation zusätzlich angelegt hat, entfernen: nur Deutschland, Euro, Deutsch
        $kanal = Uuid::fromHexToBytes($id);
        $this->db->executeStatement('DELETE FROM sales_channel_country WHERE sales_channel_id = ? AND country_id <> ?', [$kanal, Uuid::fromHexToBytes($landId)]);
        $this->db->executeStatement('DELETE FROM sales_channel_currency WHERE sales_channel_id = ? AND currency_id <> ?', [$kanal, Uuid::fromHexToBytes(Defaults::CURRENCY)]);
        $this->db->executeStatement('DELETE FROM sales_channel_payment_method WHERE sales_channel_id = ? AND payment_method_id <> ?', [$kanal, Uuid::fromHexToBytes($zahlart)]);
        $this->db->executeStatement(
            'DELETE FROM sales_channel_shipping_method WHERE sales_channel_id = ? AND shipping_method_id NOT IN (?, ?)',
            [$kanal, Uuid::fromHexToBytes($versandarten[0]), Uuid::fromHexToBytes($versandarten[1])],
        );
        $this->io->writeln('Verkaufskanal: Deutsch, Euro, Deutschland, Rechnung, Versand per Post.');
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

    /** @param array<int, string> $steuern */
    private function artikel(string $salesChannelId, array $steuern, string $lieferzeitPost, string $lieferzeitSofort): void
    {
        $hersteller = $this->id('hersteller.ketteler');
        $this->repo('product_manufacturer')->upsert([['id' => $hersteller, 'name' => 'KETTELER-Verlag']], $this->context);

        $zeilen = [];
        foreach ($this->json('artikel.json')['artikel'] as $a) {
            $id = $this->id('artikel.' . $a['nummer']);
            $online = $a['artikelart'] === 'online';
            $satz = (int) $a['steuer'];
            $zeilen[] = [
                'id' => $id,
                'productNumber' => $a['nummer'],
                'name' => $a['name'],
                'description' => '<p>' . htmlspecialchars($a['beschreibung']) . '</p>',
                'active' => true,
                'taxId' => $steuern[$satz],
                'manufacturerId' => $hersteller,
                'price' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => (float) $a['preis'],
                    'net' => round($a['preis'] / (1 + $satz / 100), 2),
                    'linked' => true,
                ]],
                'stock' => 1000,
                'isCloseout' => false,
                'shippingFree' => $online,
                'weight' => (float) $a['gewicht'],
                'deliveryTimeId' => $online ? $lieferzeitSofort : $lieferzeitPost,
                'customFields' => [
                    'ketteler_artikelart' => $a['artikelart'],
                    'ketteler_produkt' => $a['produkt'],
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

    private function einstellungen(): void
    {
        $werte = [
            'core.basicInformation.shopName' => 'KETTELER-Verlag',
            'core.basicInformation.email' => 'shop@ketteler-verlag.test',
            'core.loginRegistration.showAccountTypeSelection' => true,
            'core.cart.showDeliveryTime' => true,
        ];
        foreach ($werte as $schluessel => $wert) {
            $this->config->set($schluessel, $wert);
        }
        $this->io->writeln('Shop-Name und Grundeinstellungen gesetzt.');
    }
}
