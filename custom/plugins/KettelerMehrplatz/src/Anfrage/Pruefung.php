<?php declare(strict_types=1);

namespace Ketteler\Mehrplatz\Anfrage;

/** Prüft die Formulardaten. Gibt bereinigte Daten und Fehler je Feld zurück. */
final class Pruefung
{
    public const MIN_ANZAHL = 2;
    public const MAX_ANZAHL = 10000;

    /**
     * @param array<string, mixed> $eingabe
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>} [Daten, Fehler (Feld => Snippet-Schlüssel)]
     */
    public static function pruefe(array $eingabe): array
    {
        $text = static fn (string $feld, int $max): string => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) ($eingabe[$feld] ?? '')) ?? ''), 0, $max);
        $daten = [
            'einrichtung' => $text('einrichtung', 255),
            'ansprechperson' => $text('ansprechperson', 255),
            'email' => mb_strtolower($text('email', 255)),
            'telefon' => $text('telefon', 100),
            'anzahl' => filter_var($eingabe['anzahl'] ?? null, \FILTER_VALIDATE_INT),
            'nachricht' => $text('nachricht', 5000),
            'artikel' => $text('artikel', 255),
            'werk' => $text('werk', 50),
        ];
        $fehler = [];
        foreach (['einrichtung', 'ansprechperson', 'telefon'] as $pflicht) {
            if ($daten[$pflicht] === '') {
                $fehler[$pflicht] = 'ketteler.mehrplatz.fehler.pflicht';
            }
        }
        if (filter_var($daten['email'], \FILTER_VALIDATE_EMAIL) === false) {
            $fehler['email'] = 'ketteler.mehrplatz.fehler.email';
        }
        if ($daten['anzahl'] === false || $daten['anzahl'] < self::MIN_ANZAHL || $daten['anzahl'] > self::MAX_ANZAHL) {
            $fehler['anzahl'] = 'ketteler.mehrplatz.fehler.anzahl';
        }
        foreach (['telefon', 'nachricht', 'artikel', 'werk'] as $frei) {
            $daten[$frei] = $daten[$frei] === '' ? null : $daten[$frei];
        }

        return [$daten, $fehler];
    }
}
