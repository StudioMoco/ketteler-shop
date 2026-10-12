<?php declare(strict_types=1);

namespace Ketteler\Steuern\Ustid;

/** USt-IdNr. normalisieren und in Länderkennzeichen und Nummer zerlegen (Griechenland: EL = ISO GR). */
final class UstId
{
    /** VIES-Länderkennzeichen der EU-Staaten => ISO-Code */
    private const EU = ['AT' => 'AT', 'BE' => 'BE', 'BG' => 'BG', 'HR' => 'HR', 'CY' => 'CY', 'CZ' => 'CZ', 'DK' => 'DK',
        'EE' => 'EE', 'FI' => 'FI', 'FR' => 'FR', 'DE' => 'DE', 'EL' => 'GR', 'HU' => 'HU', 'IE' => 'IE', 'IT' => 'IT',
        'LV' => 'LV', 'LT' => 'LT', 'LU' => 'LU', 'MT' => 'MT', 'NL' => 'NL', 'PL' => 'PL', 'PT' => 'PT', 'RO' => 'RO',
        'SK' => 'SK', 'SI' => 'SI', 'ES' => 'ES', 'SE' => 'SE'];

    public static function normalisieren(string $ustid): string
    {
        return strtoupper((string) preg_replace('/[\s.\-\/]/', '', $ustid));
    }

    /** @return array{kennzeichen: string, nummer: string, iso: string}|null null = kein EU-Format */
    public static function zerlegen(string $ustid): ?array
    {
        $n = self::normalisieren($ustid);
        if (!preg_match('/^([A-Z]{2})([0-9A-Z+*]{2,13})$/', $n, $teile) || !isset(self::EU[$teile[1]])) {
            return null;
        }

        return ['kennzeichen' => $teile[1], 'nummer' => $teile[2], 'iso' => self::EU[$teile[1]]];
    }
}
