<?php declare(strict_types=1);

namespace Ketteler\Steuern\Ustid;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fragt die VIES-REST-Schnittstelle der EU-Kommission ab. Adresse aus KETTELER_VIES_URL (lokal die Attrappe),
 * sonst die offizielle Adresse. Nicht erreichbar oder Fehler eines Mitgliedstaats: NICHT_PRUEFBAR (nie „gültig“).
 * Mit der USt-IdNr. des Anfragenden (Ketteler, Einstellung im Plugin) liefert VIES eine Abfragenummer
 * (requestIdentifier) als Nachweis der qualifizierten Bestätigung; ohne sie nur „gültig/ungültig“.
 */
class ViesClient
{
    public const OFFIZIELL = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

    public function __construct(private readonly HttpClientInterface $http, private readonly ?string $adresse = null)
    {
    }

    public function pruefen(string $ustid, ?string $anfragende = null): Ergebnis
    {
        $jetzt = (new \DateTimeImmutable())->format(\DATE_ATOM);
        $n = UstId::normalisieren($ustid);
        $teile = UstId::zerlegen($n);
        if ($teile === null) {
            return new Ergebnis(Ergebnis::FORMAT, $n, null, $jetzt, null, 'Kein gültiges Format einer EU-USt-IdNr.');
        }
        if ($teile['iso'] === 'DE') {
            return new Ergebnis(Ergebnis::INLAND, $n, 'DE', $jetzt, null, 'Deutsche USt-IdNr.: keine Steuerbefreiung.');
        }
        $adresse = $this->adresse ?? (trim((string) ($_SERVER['KETTELER_VIES_URL'] ?? getenv('KETTELER_VIES_URL') ?: '')) ?: self::OFFIZIELL);
        $anfrage = ['countryCode' => $teile['kennzeichen'], 'vatNumber' => $teile['nummer']];
        $eigen = $anfragende !== null && trim($anfragende) !== '' ? UstId::zerlegen(UstId::normalisieren($anfragende)) : null;
        if ($eigen !== null) {
            $anfrage['requesterMemberStateCode'] = $eigen['kennzeichen'];
            $anfrage['requesterNumber'] = $eigen['nummer'];
        }
        $anfragendeNr = $eigen !== null ? $eigen['kennzeichen'] . $eigen['nummer'] : null;
        try {
            $antwort = $this->http->request('POST', $adresse, [
                'json' => $anfrage,
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 8,
                'max_duration' => 15,
            ]);
            $status = $antwort->getStatusCode();
            $daten = json_decode($antwort->getContent(false), true);
        } catch (ExceptionInterface $e) {
            return new Ergebnis(Ergebnis::NICHT_PRUEFBAR, $n, $teile['iso'], $jetzt, null, 'VIES nicht erreichbar: ' . $e->getMessage(), null, null, $anfragendeNr);
        }
        if ($status !== 200 || !\is_array($daten) || !\array_key_exists('valid', $daten) || !\is_bool($daten['valid'])) {
            $fehler = \is_array($daten) ? (string) ($daten['errorWrappers'][0]['error'] ?? 'unbekannte Antwort') : 'keine Antwort';

            return new Ergebnis(Ergebnis::NICHT_PRUEFBAR, $n, $teile['iso'], $jetzt, null, \sprintf('VIES antwortet %d: %s', $status, $fehler), null, null, $anfragendeNr);
        }
        $name = \is_string($daten['name'] ?? null) && $daten['name'] !== '---' ? $daten['name'] : null;
        $nummer = \is_string($daten['requestIdentifier'] ?? null) && $daten['requestIdentifier'] !== '' ? $daten['requestIdentifier'] : null;
        $datum = \is_string($daten['requestDate'] ?? null) ? $daten['requestDate'] : null;

        return new Ergebnis($daten['valid'] ? Ergebnis::GUELTIG : Ergebnis::UNGUELTIG, $n, $teile['iso'], $jetzt, $name, $daten['valid'] ? null : 'VIES: Nummer nicht gültig.', $nummer, $datum, $anfragendeNr);
    }
}
