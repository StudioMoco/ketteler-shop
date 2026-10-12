<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Schnittstelle;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * POST /api/shop/ereignisse des Redaktionssystems. Adresse und Geheimnis aus der Umgebung
 * (KETTELER_LIZENZ_URL, KETTELER_LIZENZ_GEHEIMNIS).
 */
class LizenzSchnittstelle
{
    /** @param (callable(): int)|null $uhr */
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly ?string $adresse = null,
        private readonly ?string $geheimnis = null,
        private readonly mixed $uhr = null,
    ) {
    }

    /**
     * @param array<string, mixed> $daten
     *
     * @throws VoruebergehenderFehler bei 5xx, Zeitüberschreitung oder Netzfehler
     * @throws AbgelehntFehler bei 4xx (außer den erlaubten Codes)
     *
     * @param list<int> $erlaubt 4xx-Codes, die als Antwort zurückgegeben statt geworfen werden (z. B. 404 beim Storno)
     */
    public function senden(array $daten, array $erlaubt = []): Antwort
    {
        $adresse = $this->adresse ?? self::env('KETTELER_LIZENZ_URL');
        $geheimnis = $this->geheimnis ?? self::env('KETTELER_LIZENZ_GEHEIMNIS');
        if ($adresse === '' || $geheimnis === '') {
            throw new VoruebergehenderFehler('Lizenz-Schnittstelle nicht eingerichtet (KETTELER_LIZENZ_URL, KETTELER_LIZENZ_GEHEIMNIS).');
        }

        // Genau diese Bytes werden signiert und gesendet
        $koerper = json_encode($daten, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
        $zeit = (string) ($this->uhr !== null ? ($this->uhr)() : time());

        try {
            $antwort = $this->http->request('POST', $adresse, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Ketteler-Zeit' => $zeit,
                    'X-Ketteler-Signatur' => Signatur::bilde($geheimnis, $zeit, $koerper),
                ],
                'body' => $koerper,
                'timeout' => 10,
                'max_duration' => 20,
            ]);
            $status = $antwort->getStatusCode();
            $inhalt = $antwort->getContent(false);
        } catch (ExceptionInterface $e) {
            throw new VoruebergehenderFehler('Lizenz-Schnittstelle nicht erreichbar: ' . $e->getMessage(), 0, $e);
        }

        $json = json_decode($inhalt, true);
        $json = \is_array($json) ? $json : [];
        if ($status >= 500) {
            throw new VoruebergehenderFehler(\sprintf('Lizenz-Schnittstelle antwortet %d.', $status));
        }
        if ($status >= 400 && !\in_array($status, $erlaubt, true)) {
            throw new AbgelehntFehler(\sprintf('Lizenz-Schnittstelle lehnt ab (%d): %s', $status, mb_substr($inhalt, 0, 500)), $status);
        }

        return new Antwort($status, $json);
    }

    private static function env(string $name): string
    {
        $wert = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($wert) ? trim($wert) : '';
    }
}
