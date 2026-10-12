<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Tests\Unit;

use Ketteler\Lizenzen\Schnittstelle\AbgelehntFehler;
use Ketteler\Lizenzen\Schnittstelle\LizenzSchnittstelle;
use Ketteler\Lizenzen\Schnittstelle\Signatur;
use Ketteler\Lizenzen\Schnittstelle\VoruebergehenderFehler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class LizenzSchnittstelleTest extends TestCase
{
    private const GEHEIMNIS = 'geheim-nur-fuer-tests';

    private function schnittstelle(MockHttpClient $http): LizenzSchnittstelle
    {
        return new LizenzSchnittstelle($http, 'http://attrappe.test/api/shop/ereignisse', self::GEHEIMNIS, static fn () => 1790000000);
    }

    public function testSignaturWieImRedaktionssystem(): void
    {
        // Gegenprobe mit der Formel aus docs/lizenzen/shop-schnittstelle.md
        $koerper = '{"ereignis_id":"order-4711-1-lizenz","art":"lizenz_anlegen"}';
        self::assertSame('sha256=' . hash_hmac('sha256', '1790000000.' . $koerper, 'g'), Signatur::bilde('g', '1790000000', $koerper));
    }

    public function testSendetSigniertesJsonUndLiefertAntwort(): void
    {
        $gesendet = null;
        $http = new MockHttpClient(function (string $methode, string $url, array $optionen) use (&$gesendet) {
            $gesendet = $optionen;

            return new MockResponse('{"lizenz_id":9,"kunde_id":7,"status":"aktiv","ende":null}', ['http_code' => 201]);
        });
        $antwort = $this->schnittstelle($http)->senden(['ereignis_id' => 'e1', 'name' => 'Jürgen Müller', 'adresse' => 'a/b']);

        self::assertSame(201, $antwort->status);
        self::assertSame(9, $antwort->daten['lizenz_id']);
        self::assertSame('{"ereignis_id":"e1","name":"Jürgen Müller","adresse":"a/b"}', $gesendet['body']);
        $kopf = implode("\n", $gesendet['headers']);
        self::assertStringContainsString('X-Ketteler-Zeit: 1790000000', $kopf);
        self::assertStringContainsString('X-Ketteler-Signatur: ' . Signatur::bilde(self::GEHEIMNIS, '1790000000', $gesendet['body']), $kopf);
    }

    /** @return iterable<string, array{0: MockResponse, 1: class-string}> */
    public static function fehler(): iterable
    {
        yield '503' => [new MockResponse('', ['http_code' => 503]), VoruebergehenderFehler::class];
        yield '500' => [new MockResponse('kaputt', ['http_code' => 500]), VoruebergehenderFehler::class];
        yield 'nicht erreichbar' => [new MockResponse('', ['error' => 'Verbindung abgelehnt']), VoruebergehenderFehler::class];
        yield '401' => [new MockResponse('', ['http_code' => 401]), AbgelehntFehler::class];
        yield '422' => [new MockResponse('{"errors":{"email":["x"]}}', ['http_code' => 422]), AbgelehntFehler::class];
        yield '404 nicht erlaubt' => [new MockResponse('', ['http_code' => 404]), AbgelehntFehler::class];
    }

    /**
     * @dataProvider fehler
     *
     * @param class-string<\Throwable> $erwartet
     */
    public function testFehlerArten(MockResponse $antwort, string $erwartet): void
    {
        $this->expectException($erwartet);
        $this->schnittstelle(new MockHttpClient([$antwort]))->senden(['ereignis_id' => 'e1']);
    }

    public function testErlaubter404KommtAlsAntwort(): void
    {
        $antwort = $this->schnittstelle(new MockHttpClient([new MockResponse('{"message":"keine"}', ['http_code' => 404])]))->senden(['ereignis_id' => 'e1'], [404]);
        self::assertSame(404, $antwort->status);
    }

    public function testOhneEinrichtungVoruebergehend(): void
    {
        $this->expectException(VoruebergehenderFehler::class);
        (new LizenzSchnittstelle(new MockHttpClient([]), '', ''))->senden([]);
    }
}
