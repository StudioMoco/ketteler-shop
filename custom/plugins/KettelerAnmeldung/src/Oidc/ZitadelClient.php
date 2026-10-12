<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Oidc;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Spricht mit ZITADEL. Der Browser nutzt die öffentliche Adresse (Aussteller); Anfragen vom Server gehen an die
 * interne Adresse mit dem Host der öffentlichen, damit ZITADEL die Instanz erkennt.
 */
class ZitadelClient
{
    /** @var array<string, mixed>|null */
    private ?array $discovery = null;

    /** @var array<string, mixed>|null */
    private ?array $jwks = null;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly Konfiguration $konfiguration,
    ) {
    }

    /** @param array<string, string> $parameter */
    public function anmeldeAdresse(array $parameter): string
    {
        return $this->endpunkt('authorization_endpoint') . '?' . http_build_query($parameter, '', '&', \PHP_QUERY_RFC3986);
    }

    public function abmeldeAdresse(string $idToken, string $zurueck): string
    {
        return $this->endpunkt('end_session_endpoint') . '?' . http_build_query([
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => $zurueck,
        ], '', '&', \PHP_QUERY_RFC3986);
    }

    /** @return array<string, mixed> Antwort des Token-Endpunkts */
    public function codeEinloesen(string $code, string $verifier, string $redirectUri, string $clientId): array
    {
        $antwort = $this->anfrage('POST', $this->endpunkt('token_endpoint'), [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'code_verifier' => $verifier,
            ],
        ]);
        if (!\is_string($antwort['id_token'] ?? null)) {
            throw new OidcFehler('Token-Antwort ohne id_token.');
        }

        return $antwort;
    }

    /** @return array<string, mixed> */
    public function schluessel(): array
    {
        return $this->jwks ??= $this->anfrage('GET', $this->endpunkt('jwks_uri'));
    }

    public function endpunkt(string $name): string
    {
        $this->discovery ??= $this->anfrage('GET', $this->konfiguration->aussteller() . '/.well-known/openid-configuration');
        $wert = $this->discovery[$name] ?? null;
        if (!\is_string($wert) || $wert === '') {
            throw new OidcFehler('Discovery ohne ' . $name . '.');
        }

        return $wert;
    }

    /**
     * @param array<string, mixed> $optionen
     *
     * @return array<string, mixed>
     */
    private function anfrage(string $methode, string $adresse, array $optionen = []): array
    {
        $oeffentlich = $this->konfiguration->aussteller();
        $intern = $this->konfiguration->intern();
        if ($intern !== $oeffentlich && str_starts_with($adresse, $oeffentlich)) {
            $adresse = $intern . substr($adresse, \strlen($oeffentlich));
            $teile = parse_url($oeffentlich);
            $optionen['headers']['Host'] = ($teile['host'] ?? '') . (isset($teile['port']) ? ':' . $teile['port'] : '');
        }
        $optionen['timeout'] ??= 10;
        try {
            $antwort = $this->http->request($methode, $adresse, $optionen);
            $status = $antwort->getStatusCode();
            $inhalt = $antwort->getContent(false);
        } catch (ExceptionInterface $e) {
            throw new OidcFehler('ZITADEL nicht erreichbar: ' . $e->getMessage(), 0, $e);
        }
        if ($status >= 400) {
            throw new OidcFehler(\sprintf('ZITADEL antwortet %d auf %s: %s', $status, parse_url($adresse, \PHP_URL_PATH), mb_substr($inhalt, 0, 300)));
        }
        $daten = json_decode($inhalt, true);
        if (!\is_array($daten)) {
            throw new OidcFehler('ZITADEL-Antwort ist kein JSON.');
        }

        return $daten;
    }
}
