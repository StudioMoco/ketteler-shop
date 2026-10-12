<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Widerruf;

/**
 * Ergebnis der Zuordnung eines Widerrufs zu einer Bestellung. Reine Logik, ohne Datenbank (testbar).
 *
 * Eine Bestellung gilt nur als zugeordnet, wenn Bestellnummer und E-Mail zusammenpassen oder der angemeldete Kunde der
 * Besteller ist. Online-Positionen mit Zustimmung zum sofortigen Beginn werden für die Verwaltung markiert; gesperrt oder
 * beendet wird nichts.
 */
final class Auswertung
{
    /**
     * @param list<array{id: string, label: string, nummer: string, online: bool}> $positionen gewählte Positionen
     * @param array{zugestimmt: string, text: string}|null $zustimmung Zustimmung „sofortiger Beginn“ an der Bestellung
     */
    public function __construct(
        public readonly ?string $orderId,
        public readonly string $bestellnummer,
        public readonly bool $zugeordnet,
        public readonly bool $alle,
        public readonly array $positionen,
        public readonly ?array $zustimmung,
        public readonly string $grund,
    ) {
    }

    /**
     * @param array<string, mixed>|null $bestellung ['id', 'nummer', 'email', 'kundeId', 'zustimmung', 'positionen' => list<…>]
     * @param list<string> $gewaehlteIds Positions-IDs aus dem Formular (angemeldete Kunden)
     */
    public static function auswerten(?array $bestellung, string $nummer, string $email, ?string $kundeId, array $gewaehlteIds): self
    {
        $nummer = trim($nummer);
        if ($bestellung === null) {
            return new self(null, $nummer, false, true, [], null, 'Nicht zugeordnet, bitte von Hand prüfen: Zu dieser Nummer gibt es im Shop keine Bestellung (vielleicht eine Vertrags- oder Abonummer aus weclapp oder ein Tippfehler).');
        }
        $passt = ($kundeId !== null && $kundeId === $bestellung['kundeId'])
            || ($email !== '' && mb_strtolower(trim($email)) === mb_strtolower((string) $bestellung['email']));
        if (!$passt) {
            return new self(null, $nummer, false, true, [], null, 'Nicht zugeordnet, bitte von Hand prüfen: Die Bestellnummer gibt es, aber E-Mail-Adresse und Konto passen nicht zur Bestellung.');
        }
        $alle = $gewaehlteIds === [];
        $positionen = array_values(array_filter(
            $bestellung['positionen'],
            static fn (array $p): bool => $alle || \in_array($p['id'], $gewaehlteIds, true),
        ));
        if ($positionen === []) {
            $alle = true;
            $positionen = $bestellung['positionen'];
        }

        return new self((string) $bestellung['id'], (string) $bestellung['nummer'], true, $alle, $positionen, $bestellung['zustimmung'] ?? null, '');
    }

    public function onlineMitZustimmung(): bool
    {
        if ($this->zustimmung === null) {
            return false;
        }
        foreach ($this->positionen as $p) {
            if ($p['online']) {
                return true;
            }
        }

        return false;
    }

    /** Für die Eingangsbestätigung an den Kunden: verrät nicht, ob es die Bestellung gibt. */
    public function umfangFuerKunde(): string
    {
        if (!$this->zugeordnet || $this->alle) {
            return 'die gesamte Bestellung bzw. die in Ihrer Nachricht genannten Artikel';
        }

        return implode('; ', array_map(static fn (array $p): string => $p['label'] . ($p['nummer'] !== '' ? ' (' . $p['nummer'] . ')' : ''), $this->positionen));
    }

    /** Kurztext der Positionen, z. B. „TEST-P-001 Musterbuch (Print); TEST-O-EK Eichstätter (Online-Zugang)“. */
    public function positionenText(): string
    {
        if (!$this->zugeordnet) {
            return '';
        }
        $teile = array_map(static fn (array $p): string => trim($p['nummer'] . ' ' . $p['label']) . ($p['online'] ? ' (Online-Zugang)' : ''), $this->positionen);

        return ($this->alle ? 'alle Positionen: ' : '') . implode('; ', $teile);
    }

    /** Hinweis für die Verwaltung (Händler-Mail und Bestellung). */
    public function hinweisVerwaltung(): string
    {
        if (!$this->zugeordnet) {
            return $this->grund;
        }
        $text = 'Zugeordnet zu Bestellung ' . $this->bestellnummer . '. Positionen: ' . $this->positionenText() . '.';
        if ($this->onlineMitZustimmung()) {
            $text .= ' Achtung: Die Bestellung enthält einen Online-Zugang, für den der Kunde am ' . $this->zustimmung['zugestimmt']
                . ' dem sofortigen Beginn zugestimmt hat. Das Widerrufsrecht kann damit erloschen sein. Bitte prüfen;'
                . ' die Lizenz bleibt bis zur Entscheidung der Verwaltung bestehen.';
        }

        return $text;
    }
}
