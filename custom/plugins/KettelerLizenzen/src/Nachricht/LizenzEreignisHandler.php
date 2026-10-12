<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Nachricht;

use Ketteler\Lizenzen\Schnittstelle\AbgelehntFehler;
use Ketteler\Lizenzen\Schnittstelle\LizenzSchnittstelle;
use Ketteler\Lizenzen\Schnittstelle\VoruebergehenderFehler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

/**
 * Arbeitet ein Lizenz-Ereignis aus der Warteschlange ab.
 * Stände je Position (Zusatzfeld ketteler_lizenz.status): offen → angelegt | wartet (wird wiederholt) | fehler;
 * nach Storno: storniert.
 */
class LizenzEreignisHandler
{
    public function __construct(
        private readonly PositionenSpeicher $positionen,
        private readonly LizenzSchnittstelle $schnittstelle,
        private readonly Meldung $meldung,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(LizenzEreignis $ereignis): void
    {
        $position = $this->positionen->laden($ereignis->orderId, $ereignis->positionId);
        if ($position === null || $position->produkt === null) {
            $this->logger->warning('Ketteler-Lizenz: Position nicht gefunden oder ohne Online-Werk.', ['ereignis' => $ereignis->ereignisId()]);

            return;
        }

        $ereignis->art === LizenzEreignis::STORNIEREN ? $this->stornieren($ereignis, $position) : $this->anlegen($ereignis, $position);
    }

    private function anlegen(LizenzEreignis $ereignis, Position $p): void
    {
        if (\in_array($p->status(), ['angelegt', 'storniert'], true)) {
            return;
        }
        if ($p->bestellungStorniert) {
            $this->positionen->speichern($p->positionId, ['status' => 'storniert', 'hinweis' => 'Bestellung vor der Lizenzvergabe storniert']);

            return;
        }

        $daten = [
            'ereignis_id' => $ereignis->ereignisId(),
            'art' => LizenzEreignis::ANLEGEN,
            'email' => $p->email,
            'name' => $p->name,
            'produkt' => $p->produkt,
            'typ' => 'einzel',
            'beginn' => $p->bestelldatum,
            'bestellnummer' => $p->bestellnummer,
        ];
        $antwort = $this->senden($ereignis, $p, $daten);
        if ($antwort === null) {
            return;
        }
        $this->positionen->speichern($p->positionId, [
            'status' => 'angelegt',
            'lizenz_id' => $antwort->daten['lizenz_id'] ?? null,
            'fehler' => null,
        ]);
        if ($p->menge > 1) {
            // Mehrplatz vergibt die Verwaltung (Entscheidung 12.10.2026)
            $this->meldung->melden($p, 'Mehrplatz', \sprintf('Menge %d bestellt, eine Einzellizenz angelegt. Weitere Plätze bitte in „Kunden & Lizenzen“ vergeben.', $p->menge));
        }
    }

    private function stornieren(LizenzEreignis $ereignis, Position $p): void
    {
        if ($p->status() === 'storniert') {
            return;
        }
        if ($p->status() !== 'angelegt') {
            // Noch keine Lizenz: Anlage wird damit verhindert (anlegen() prüft den Stand)
            $this->positionen->speichern($p->positionId, ['status' => 'storniert', 'hinweis' => 'Storno vor der Lizenzvergabe']);

            return;
        }

        $daten = [
            'ereignis_id' => $ereignis->ereignisId(),
            'art' => LizenzEreignis::STORNIEREN,
            'email' => $p->email,
            'produkt' => $p->produkt,
            'bestellnummer' => $p->bestellnummer,
            'grund' => 'Storno der Bestellung im Shop',
        ];
        $antwort = $this->senden($ereignis, $p, $daten, [404]);
        if ($antwort === null) {
            return;
        }
        $this->positionen->speichern($p->positionId, [
            'status' => 'storniert',
            'ende' => $antwort->daten['ende'] ?? null,
            'hinweis' => $antwort->status === 404 ? 'Schnittstelle kennt zu dieser Bestellung keine Lizenz' : null,
            'fehler' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $daten
     * @param list<int> $erlaubt
     */
    private function senden(LizenzEreignis $ereignis, Position $p, array $daten, array $erlaubt = []): ?\Ketteler\Lizenzen\Schnittstelle\Antwort
    {
        try {
            return $this->schnittstelle->senden($daten, $erlaubt);
        } catch (VoruebergehenderFehler $e) {
            $this->positionen->speichern($p->positionId, [
                'status' => $ereignis->art === LizenzEreignis::ANLEGEN ? 'wartet' : $p->status(),
                'storno' => $ereignis->art === LizenzEreignis::STORNIEREN ? 'wartet' : ($p->lizenz['storno'] ?? null),
                'versuche' => (int) ($p->lizenz['versuche'] ?? 0) + 1,
                'fehler' => $e->getMessage(),
            ]);
            $this->logger->warning('Ketteler-Lizenz: wird wiederholt. ' . $e->getMessage(), ['ereignis' => $ereignis->ereignisId()]);

            // Messenger wiederholt nach der Wiederholungsregel der Warteschlange ketteler_lizenz
            throw new RecoverableMessageHandlingException($e->getMessage(), 0, $e);
        } catch (AbgelehntFehler $e) {
            $this->positionen->speichern($p->positionId, [
                'status' => $ereignis->art === LizenzEreignis::ANLEGEN ? 'fehler' : $p->status(),
                'storno' => $ereignis->art === LizenzEreignis::STORNIEREN ? 'fehler' : ($p->lizenz['storno'] ?? null),
                'fehler' => $e->getMessage(),
            ]);
            $this->logger->error('Ketteler-Lizenz: abgelehnt. ' . $e->getMessage(), ['ereignis' => $ereignis->ereignisId()]);
            $this->meldung->melden($p, $ereignis->art === LizenzEreignis::ANLEGEN ? 'Lizenz nicht angelegt' : 'Storno nicht übertragen', $e->getMessage());

            return null;
        }
    }
}
