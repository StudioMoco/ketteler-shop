<?php declare(strict_types=1);

namespace Ketteler\Mehrplatz\Kuendigung;

/**
 * Prüft das Kündigungsformular (§ 312k BGB): Pflicht sind Angaben, mit denen der Vertrag zu finden ist (Name, E-Mail,
 * Abo- oder Rechnungsnummer), die Art der Kündigung und der Zeitpunkt. Gibt bereinigte Daten und Fehler je Feld zurück.
 */
final class Pruefung
{
    public const ARTEN = ['ordentlich', 'ausserordentlich'];
    public const TERMINE = ['naechstmoeglich', 'wunschtermin'];

    /**
     * @param array<string, mixed> $eingabe
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function pruefe(array $eingabe, ?\DateTimeImmutable $heute = null): array
    {
        $text = static fn (string $feld, int $max): string => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) ($eingabe[$feld] ?? '')) ?? ''), 0, $max);
        $daten = [
            'vertrag' => $text('vertrag', 100),
            'vorname' => $text('vorname', 100),
            'nachname' => $text('nachname', 100),
            'firma' => $text('firma', 255),
            'strasse' => $text('strasse', 255),
            'plz' => $text('plz', 20),
            'ort' => $text('ort', 100),
            'email' => mb_strtolower($text('email', 255)),
            'email2' => mb_strtolower($text('email2', 255)),
            'art' => $text('art', 30),
            'grund' => $text('grund', 2000),
            'termin' => $text('termin', 30),
            'datum' => $text('datum', 10),
        ];
        $fehler = [];
        foreach (['vertrag', 'vorname', 'nachname'] as $pflicht) {
            if ($daten[$pflicht] === '') {
                $fehler[$pflicht] = 'ketteler.kuendigung.fehler.pflicht';
            }
        }
        if (filter_var($daten['email'], \FILTER_VALIDATE_EMAIL) === false) {
            $fehler['email'] = 'ketteler.kuendigung.fehler.email';
        } elseif ($daten['email2'] !== $daten['email']) {
            $fehler['email2'] = 'ketteler.kuendigung.fehler.email2';
        }
        if (!\in_array($daten['art'], self::ARTEN, true)) {
            $fehler['art'] = 'ketteler.kuendigung.fehler.pflicht';
        }
        if (!\in_array($daten['termin'], self::TERMINE, true)) {
            $fehler['termin'] = 'ketteler.kuendigung.fehler.pflicht';
        } elseif ($daten['termin'] === 'wunschtermin') {
            $datum = \DateTimeImmutable::createFromFormat('!Y-m-d', $daten['datum']);
            if ($datum === false || $datum < ($heute ?? new \DateTimeImmutable('today'))) {
                $fehler['datum'] = 'ketteler.kuendigung.fehler.datum';
            }
        } else {
            $daten['datum'] = '';
        }
        unset($daten['email2']);

        return [$daten, $fehler];
    }
}
