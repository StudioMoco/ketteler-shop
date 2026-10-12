<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Widerruf;

use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Stand „Widerruf bearbeitet“ an einer Bestellung (Zusatzfeld ketteler_widerruf_bearbeitung). Nur intern für die
 * Verwaltung. Jede Änderung (bearbeitet oder zurückgenommen) kommt mit Zeit und Bearbeiter in den Verlauf; der Verlauf
 * wird nie gekürzt, damit nachvollziehbar bleibt, wer wann was gemacht hat.
 */
final class Bearbeitung
{
    public const FELD = 'ketteler_widerruf_bearbeitung';
    public const TAG_BEARBEITET = 'Widerruf bearbeitet';

    public static function tagId(): string
    {
        return Uuid::fromStringToHex('ketteler.tag.widerruf-bearbeitet');
    }

    /**
     * @param array<string, mixed>|null $bisher
     *
     * @return array<string, mixed> neuer Stand für das Zusatzfeld
     */
    public static function umschalten(?array $bisher, bool $bearbeitet, \DateTimeImmutable $zeit, string $benutzer, ?string $benutzerId): array
    {
        $verlauf = \is_array($bisher['verlauf'] ?? null) ? array_values($bisher['verlauf']) : [];
        if ((bool) ($bisher['bearbeitet'] ?? false) === $bearbeitet) {
            throw new \DomainException($bearbeitet ? 'Der Widerruf ist bereits als bearbeitet markiert.' : 'Der Widerruf ist nicht als bearbeitet markiert.');
        }
        $eintrag = [
            'aktion' => $bearbeitet ? 'bearbeitet' : 'zurueckgenommen',
            'zeit' => $zeit->format(\DATE_ATOM),
            'benutzer' => $benutzer,
            'benutzerId' => $benutzerId,
        ];
        $verlauf[] = $eintrag;

        return self::stand($bearbeitet, $eintrag, $verlauf);
    }

    /**
     * Neuer Widerruf an einer schon bearbeiteten Bestellung: wieder offen, mit Vermerk im Verlauf.
     *
     * @param array<string, mixed>|null $bisher
     *
     * @return array<string, mixed>|null null, wenn nichts zu ändern ist (nie bearbeitet oder schon offen)
     */
    public static function neuerEingang(?array $bisher, \DateTimeImmutable $zeit): ?array
    {
        if (!(bool) ($bisher['bearbeitet'] ?? false)) {
            return null;
        }
        $verlauf = \is_array($bisher['verlauf'] ?? null) ? array_values($bisher['verlauf']) : [];
        $eintrag = ['aktion' => 'neuer_widerruf', 'zeit' => $zeit->format(\DATE_ATOM), 'benutzer' => 'System', 'benutzerId' => null];
        $verlauf[] = $eintrag;

        return self::stand(false, $eintrag, $verlauf);
    }

    /**
     * @param array<string, mixed> $eintrag
     * @param list<array<string, mixed>> $verlauf
     *
     * @return array<string, mixed>
     */
    private static function stand(bool $bearbeitet, array $eintrag, array $verlauf): array
    {
        $benutzer = $eintrag['benutzer'];
        $benutzerId = $eintrag['benutzerId'];

        return [
            'bearbeitet' => $bearbeitet,
            'zeit' => $bearbeitet ? $eintrag['zeit'] : null,
            'benutzer' => $bearbeitet ? $benutzer : null,
            'benutzerId' => $bearbeitet ? $benutzerId : null,
            'verlauf' => $verlauf,
        ];
    }
}
