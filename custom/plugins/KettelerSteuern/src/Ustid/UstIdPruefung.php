<?php declare(strict_types=1);

namespace Ketteler\Steuern\Ustid;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Prüft die USt-IdNr. eines Kunden, sobald sie gespeichert wird (Registrierung, Profil, Verwaltung), und legt das
 * Ergebnis im Zusatzfeld ketteler_ustid ab. Nur „gueltig“ führt zur Steuerbefreiung (siehe KettelerTaxDetector).
 */
class UstIdPruefung implements EventSubscriberInterface
{
    public const FELD = 'ketteler_ustid';

    public function __construct(
        private readonly EntityRepository $kunden,
        private readonly ViesClient $vies,
        private readonly LoggerInterface $logger,
    ) {
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

    public function pruefeKunde(string $kundeId, Context $context): ?Ergebnis
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
        $ergebnis = $this->vies->pruefen($ustid);
        // Nur Zusatzfelder schreiben (ohne vatIds), damit kein neuer Prüflauf entsteht
        $this->kunden->update([['id' => $kundeId, 'customFields' => [self::FELD => $ergebnis->alsFeld()]]], $context);
        $this->logger->info('Ketteler-Steuern: USt-IdNr. geprüft.', ['kunde' => $kundeId, 'status' => $ergebnis->status]);

        return $ergebnis;
    }
}
