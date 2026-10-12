<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Widerruf;

use Ketteler\Anmeldung\Checkout\OnlineArtikel;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\RevocationRequest\SalesChannel\AbstractRevocationRequestRoute;
use Shopware\Core\Content\RevocationRequest\SalesChannel\RevocationRequestRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Ergänzt die Widerrufsfunktion von Shopware (ab 6.6.10.14): Shopware verschickt nur die beiden Mails. Wir ordnen den
 * Widerruf vorher der Bestellung zu (für die Händler-Mail) und speichern ihn danach an der Bestellung
 * (Zusatzfelder ketteler_widerrufe und ketteler_widerruf_eingang, Kennzeichen „Widerruf eingegangen“).
 * Keine automatische Lizenzbeendigung: Die Verwaltung entscheidet (Storno beendet die Lizenz).
 */
class WiderrufsanfrageRoute extends AbstractRevocationRequestRoute
{
    public const FELD_LISTE = 'ketteler_widerrufe';
    public const FELD_TEXT = 'ketteler_widerruf_eingang';

    public function __construct(
        private readonly AbstractRevocationRequestRoute $inner,
        private readonly EntityRepository $bestellungen,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function tagId(): string
    {
        return Uuid::fromStringToHex('ketteler.tag.widerruf-eingegangen');
    }

    public function getDecorated(): AbstractRevocationRequestRoute
    {
        return $this->inner;
    }

    public function request(RequestDataBag $dataBag, SalesChannelContext $context): RevocationRequestRouteResponse
    {
        $gewaehlt = $dataBag->get('kettelerPositionen');
        $gewaehlt = $gewaehlt instanceof RequestDataBag ? array_values(array_filter($gewaehlt->all(), 'is_string')) : [];
        $dataBag->remove('kettelerPositionen');
        $nummer = (string) $dataBag->get('contractNumber', '');

        // Die Zuordnung ist eine Hilfe für die Verwaltung. Sie darf den Widerruf nie aufhalten: Jeder Fehler hier führt nur
        // zu „nicht zugeordnet, bitte von Hand prüfen“, die Erklärung geht trotzdem vollständig an Kunde und Verwaltung.
        try {
            $auswertung = Auswertung::auswerten(
                $this->bestellung($nummer, $context->getContext()),
                $nummer,
                (string) $dataBag->get('email', ''),
                $context->getCustomer()?->getId(),
                $gewaehlt,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Ketteler-Widerruf: Zuordnung fehlgeschlagen: ' . $e->getMessage());
            $auswertung = Auswertung::auswerten(null, $nummer, '', null, []);
        }
        $dataBag->set('kettelerStatus', $auswertung->zugeordnet ? 'zugeordnet' : 'nicht_zugeordnet');
        $dataBag->set('kettelerZuordnung', $auswertung->hinweisVerwaltung());
        $dataBag->set('kettelerUmfang', $auswertung->umfangFuerKunde());

        $antwort = $this->inner->request($dataBag, $context);

        if ($auswertung->zugeordnet && $auswertung->orderId !== null) {
            try {
                $this->speichern($auswertung, (string) $dataBag->get('comment', ''), $context->getContext());
            } catch (\Throwable $e) {
                // Mails sind verschickt; nur der Vermerk an der Bestellung fehlt
                $this->logger->error('Ketteler-Widerruf: Vermerk an der Bestellung fehlgeschlagen: ' . $e->getMessage(), ['bestellung' => $auswertung->bestellnummer]);
            }
        } else {
            $this->logger->warning('Ketteler-Widerruf: eingegangen, nicht zugeordnet.', ['grund' => $auswertung->grund]);
        }

        return $antwort;
    }

    /** @return array<string, mixed>|null */
    private function bestellung(string $nummer, Context $context): ?array
    {
        $nummer = trim($nummer);
        if ($nummer === '' || mb_strlen($nummer) > 64) {
            return null;
        }
        $criteria = (new Criteria())->addFilter(new EqualsFilter('orderNumber', $nummer))
            ->addAssociation('lineItems')->addAssociation('orderCustomer')->setLimit(1);
        $bestellung = $this->bestellungen->search($criteria, $context)->first();
        if (!$bestellung instanceof OrderEntity) {
            return null;
        }
        $positionen = [];
        foreach ($bestellung->getLineItems() ?? [] as $p) {
            if ($p->getType() !== 'product') {
                continue;
            }
            $felder = $p->getPayload()['customFields'] ?? [];
            $positionen[] = [
                'id' => $p->getId(),
                'label' => $p->getLabel(),
                'nummer' => (string) ($p->getPayload()['productNumber'] ?? ''),
                'online' => \is_array($felder) && \in_array($felder['ketteler_artikelart'] ?? null, OnlineArtikel::ARTEN, true),
            ];
        }

        return [
            'id' => $bestellung->getId(),
            'nummer' => (string) $bestellung->getOrderNumber(),
            'email' => (string) $bestellung->getOrderCustomer()?->getEmail(),
            'kundeId' => $bestellung->getOrderCustomer()?->getCustomerId(),
            'zustimmung' => $bestellung->getCustomFields()['ketteler_widerruf'] ?? null,
            'positionen' => $positionen,
        ];
    }

    private function speichern(Auswertung $a, string $kommentar, Context $context): void
    {
        $bestellung = $this->bestellungen->search(new Criteria([(string) $a->orderId]), $context)->first();
        $liste = $bestellung instanceof OrderEntity ? (array) ($bestellung->getCustomFields()[self::FELD_LISTE] ?? []) : [];
        $eintrag = [
            'eingang' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            'alle' => $a->alle,
            'positionen' => array_map(static fn (array $p): array => ['nummer' => $p['nummer'], 'label' => $p['label'], 'online' => $p['online']], $a->positionen),
            'online_mit_zustimmung' => $a->onlineMitZustimmung(),
            'kommentar' => mb_substr($kommentar, 0, 1000),
        ];
        $liste[] = $eintrag;
        $text = implode("\n\n", array_map(static fn (array $e): string => (new \DateTimeImmutable($e['eingang']))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i')
            . ' Uhr: Widerruf für ' . ($e['alle'] ? 'alle Positionen' : 'einzelne Positionen') . ': '
            . implode('; ', array_map(static fn (array $p): string => trim($p['nummer'] . ' ' . $p['label']) . ($p['online'] ? ' (Online-Zugang)' : ''), $e['positionen']))
            . ($e['online_mit_zustimmung'] ? '. Online-Zugang mit Zustimmung zum sofortigen Beginn: Widerrufsrecht prüfen.' : '.')
            . ($e['kommentar'] !== '' ? ' Kommentar: ' . $e['kommentar'] : ''), $liste));

        $this->bestellungen->update([[
            'id' => $a->orderId,
            'customFields' => [self::FELD_LISTE => $liste, self::FELD_TEXT => $text],
            'tags' => [['id' => self::tagId(), 'name' => 'Widerruf eingegangen']],
        ]], $context);
        $this->logger->info('Ketteler-Widerruf: an Bestellung gespeichert.', ['bestellung' => $a->bestellnummer]);
    }
}
