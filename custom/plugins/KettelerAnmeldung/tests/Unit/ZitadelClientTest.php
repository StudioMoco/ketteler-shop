<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Tests\Unit;

use Ketteler\Anmeldung\Oidc\Konfiguration;
use Ketteler\Anmeldung\Oidc\OidcFehler;
use Ketteler\Anmeldung\Oidc\ZitadelClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ZitadelClientTest extends TestCase
{
    private function konfiguration(string $intern): Konfiguration
    {
        $_SERVER['KETTELER_ZITADEL_URL'] = 'http://localhost:8080';
        $_SERVER['KETTELER_ZITADEL_INTERN'] = $intern;

        return new Konfiguration($this->createMock(\Shopware\Core\System\SystemConfig\SystemConfigService::class));
    }

    private static function discovery(): MockResponse
    {
        return new MockResponse(json_encode([
            'issuer' => 'http://localhost:8080',
            'authorization_endpoint' => 'http://localhost:8080/oauth/v2/authorize',
            'token_endpoint' => 'http://localhost:8080/oauth/v2/token',
            'jwks_uri' => 'http://localhost:8080/oauth/v2/keys',
            'end_session_endpoint' => 'http://localhost:8080/oidc/v1/end_session',
        ]));
    }

    public function testCodeEinloesenSchicktVerifierAnInterneAdresseMitOeffentlichemHost(): void
    {
        $anfragen = [];
        $http = new MockHttpClient(function (string $methode, string $url, array $optionen) use (&$anfragen) {
            $anfragen[] = [$methode, $url, $optionen];

            return \count($anfragen) === 1 ? self::discovery() : new MockResponse(json_encode(['id_token' => 'abc', 'access_token' => 'x']));
        });
        $client = new ZitadelClient($http, $this->konfiguration('http://zitadel:8080'));
        $antwort = $client->codeEinloesen('code-1', 'verifier-1', 'http://localhost:8000/account/oidc/rueckkehr', 'cid');

        self::assertSame('abc', $antwort['id_token']);
        [$methode, $url, $optionen] = $anfragen[1];
        self::assertSame('POST', $methode);
        self::assertSame('http://zitadel:8080/oauth/v2/token', $url);
        self::assertContains('Host: localhost:8080', $optionen['headers']);
        parse_str($optionen['body'], $body);
        self::assertSame(['grant_type' => 'authorization_code', 'code' => 'code-1', 'redirect_uri' => 'http://localhost:8000/account/oidc/rueckkehr', 'client_id' => 'cid', 'code_verifier' => 'verifier-1'], $body);
        self::assertArrayNotHasKey('client_secret', $body);
    }

    public function testAdressenFuerDenBrowserBleibenOeffentlich(): void
    {
        $client = new ZitadelClient(new MockHttpClient([self::discovery()]), $this->konfiguration('http://zitadel:8080'));
        self::assertStringStartsWith('http://localhost:8080/oauth/v2/authorize?client_id=c', $client->anmeldeAdresse(['client_id' => 'c']));
        $abmelden = $client->abmeldeAdresse('tok', 'http://localhost:8000/');
        self::assertSame('http://localhost:8080/oidc/v1/end_session?id_token_hint=tok&post_logout_redirect_uri=http%3A%2F%2Flocalhost%3A8000%2F', $abmelden);
    }

    public function testFehlerVonZitadelWirdOidcFehler(): void
    {
        $client = new ZitadelClient(new MockHttpClient([self::discovery(), new MockResponse('{"error":"invalid_grant"}', ['http_code' => 400])]), $this->konfiguration(''));
        $this->expectException(OidcFehler::class);
        $client->codeEinloesen('alt', 'v', 'http://localhost:8000/x', 'cid');
    }

    public function testNichtErreichbar(): void
    {
        $client = new ZitadelClient(new MockHttpClient([new MockResponse('', ['error' => 'Verbindung abgelehnt'])]), $this->konfiguration(''));
        $this->expectException(OidcFehler::class);
        $client->anmeldeAdresse([]);
    }
}
