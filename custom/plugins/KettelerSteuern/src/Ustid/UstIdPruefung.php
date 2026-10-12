<?php declare(strict_types=1);

namespace Ketteler\Steuern\Ustid;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prüft die USt-IdNr. eines Kunden, sobald sie gespeichert wird (Registrierung, Profil, Verwaltung), und legt das
 * Ergebnis im Zusatzfeld ketteler_ustid ab. Nur „gueltig“ führt zur Steuerbefreiung (siehe KettelerTaxDetector).
 * Nachweis: Zeit, Ergebnis und Abfragenummer der letzten Prüfung, dazu ein Verlauf der letzten Prüfungen (VERLAUF_MAX),
 * damit frühere Bestätigungen nicht durch eine neue Prüfung verloren gehen. Ist die letzte erfolgreiche Prüfung älter als
 * ein Jahr, prüft der Shop beim nächsten Kauf erneut (nachpruefenWennFaellig, aufgerufen aus dem KettelerTaxDetector).
 */
class UstIdPruefung implements EventSubscriberInterface
{
    public const FELD = 'ketteler_ustid';
    public const KONFIG_ANFRAGENDE = 'KettelerSteuern.config.anfragendeUstId';
    public const VERLAUF_MAX = 20;
    public const GUELTIGKEIT = '+1 year';

    /** @var array<string, array<string, mixed>|null> erneute Prüfungen in dieser Anfrage (je Kunde höchstens eine) */
    private array $nachgeprueft = [];

    public function __construct(
        private readonly EntityRepository $kunden,
        private readonly ViesClient $vies,
        private readonly LoggerInterface $logger,
        private readonly ?SystemConfigService $konfig = null,
    ) {
    }

    /** @param array<string, mixed>|null $pruefung */
    public static function nachpruefungFaellig(?array $pruefung, \DateTimeImmutable $jetzt): bool
    {
        if (!\is_array($pruefung) || ($pruefung['status'] ?? null) !== Ergebnis::GUELTIG || !\is_string($pruefung['geprueft'] ?? null)) {
            return false;
        }
        try {
            return (new \DateTimeImmutable($pruefung['geprueft']))->modify(self::GUELTIGKEIT) <= $jetzt;
        } catch (\Exception) {
            return true;
        }
    }

    /**
     * Neuer Stand des Zusatzfelds: aktuelle Prüfung oben, frühere im Verlauf (neueste zuerst, gekürzt).
     *
     * @param array<string, mixed>|null $bisher
     *
     * @return array<string, mixed>
     */
    public static function neuerStand(?array $bisher, Ergebnis $ergebnis): array
    {
        $verlauf = \is_array($bisher['verlauf'] ?? null) ? $bisher['verlauf'] : [];
        $eintrag = $ergebnis->alsFeld();
        array_unshift($verlauf, array_intersect_key($eintrag, array_flip(['status', 'ustid', 'geprueft', 'anfrageNummer', 'viesDatum', 'fehler'])));

        return $eintrag + ['verlauf' => \array_slice($verlauf, 0, self::VERLAUF_MAX)];
    }

    /**
     * Beim Kauf: Ist die letzte erfolgreiche Prüfung älter als ein Jahr, jetzt neu prüfen (gleiche Regeln wie sonst,
     * keine Befreiung, wenn die neue Prüfung scheitert). Gibt den neuen Stand zurück, sonst den bisherigen.
     *
     * @param array<string, mixed>|null $pruefung
     *
     * @return array<string, mixed>|null
     */
    public function nachpruefenWennFaellig(string $kundeId, ?array $pruefung, Context $context): ?array
    {
        if (\array_key_exists($kundeId, $this->nachgeprueft)) {
            return $this->nachgeprueft[$kundeId];
        }
        if (!self::nachpruefungFaellig($pruefung, new \DateTimeImmutable())) {
            return $pruefung;
        }
        $this->nachgeprueft[$kundeId] = null;
        $neu = $context->scope(Context::SYSTEM_SCOPE, fn (Context $c): ?array => $this->pruefeKunde($kundeId, $c, true));
        $this->nachgeprueft[$kundeId] = $neu;
        $this->logger->info('Ketteler-Steuern: Bestätigung älter als ein Jahr, beim Kauf neu geprüft.', ['kunde' => $kundeId, 'status' => $neu['status'] ?? null]);

        return $neu;
    }

    /**
     * @param array<string, mixed>|null $gespeichert
     *
     * @return array<string, mixed>|null
     */
    public function aktuellerStand(string $kundeId, ?array $gespeichert): ?array
    {
        return \array_key_exists($kundeId, $this->nachgeprueft) ? $this->nachgeprueft[$kundeId] : $gespeichert;
    }

    /** Zwischenspeicher zwischen zwei Anfragen leeren (Worker, langlebige Prozesse) */
    public function reset(): void
    {
        $this->nachgeprueft = [];
    }

    public static function getSubscribedEvents(): array
    {
        return [CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'geschrieben'];
    }

    public function geschrieben(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $ergebnis) {
            if (\array_key_exists('vatIds', $ergebnis->getPayload())) {
                $this->pruefeKunde((string) $ergebnis->getPrimaryKey(), $event->getContext());
            }
        }
    }

    /**
     * @return ($alsFeld is true ? array<string, mixed>|null : Ergebnis|null)
     */
    public function pruefeKunde(string $kundeId, Context $context, bool $alsFeld = false): Ergebnis|array|null
    {
        $kunde = $this->kunden->search(new Criteria([$kundeId]), $context)->first();
        if (!$kunde instanceof CustomerEntity) {
            return null;
        }
        $ustid = array_values(array_filter($kunde->getVatIds() ?? []))[0] ?? null;
        if (!\is_string($ustid) || $ustid === '') {
            if (isset($kunde->getCustomFields()[self::FELD])) {
                $this->kunden->update([['id' => $kundeId, 'customFields' => [self::FELD => null]]], $context);
            }

            return null;
        }
        $bereinigt = UstId::normalisieren($ustid);
        if ($bereinigt !== $ustid) {
            // Bereinigt speichern (ohne Leerzeichen und Punkte), sonst scheitert auch die Formatprüfung von Shopware.
            // Das Speichern löst diese Prüfung erneut aus, dann mit der bereinigten Nummer.
            $this->kunden->update([['id' => $kundeId, 'vatIds' => [$bereinigt]]], $context);

            return null;
        }
        $anfragende = $this->konfig?->getString(self::KONFIG_ANFRAGENDE);
        $ergebnis = $this->vies->pruefen($ustid, $anfragende !== '' ? $anfragende : null);
        $stand = self::neuerStand($kunde->getCustomFields()[self::FELD] ?? null, $ergebnis);
        // Nur Zusatzfelder schreiben (ohne vatIds), damit kein neuer Prüflauf entsteht
        $this->kunden->update([['id' => $kundeId, 'customFields' => [self::FELD => $stand]]], $context);
        $this->logger->info('Ketteler-Steuern: USt-IdNr. geprüft.', ['kunde' => $kundeId, 'status' => $ergebnis->status, 'anfrageNummer' => $ergebnis->anfrageNummer]);

        return $alsFeld ? $stand : $ergebnis;
    }
}
