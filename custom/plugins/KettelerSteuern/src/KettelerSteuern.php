<?php declare(strict_types=1);

namespace Ketteler\Steuern;

use Shopware\Core\Framework\Plugin;

/**
 * Umsatzsteuer im Ketteler-Shop:
 * - Schalter „EU-Lieferschwelle überschritten (OSS)“ in der Plugin-Konfiguration (Vorgabe nein, von der Steuerberatung
 *   zu bestätigen). Nein: deutsche Umsatzsteuer für alle Privatkunden in der EU. Ja: Satz des Kundenlandes für Print und
 *   Online. Sätze je Land: Resources/daten/steuersaetze.json (Platzhalter).
 * - Steuerfrei für Einrichtungen nur mit einer über VIES bestätigten USt-IdNr. eines anderen EU-Landes.
 */
class KettelerSteuern extends Plugin
{
}
