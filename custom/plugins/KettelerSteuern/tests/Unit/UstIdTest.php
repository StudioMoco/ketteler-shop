<?php declare(strict_types=1);

namespace Ketteler\Steuern\Tests\Unit;

use Ketteler\Steuern\Steuern\Steuerregeln;
use Ketteler\Steuern\Ustid\Ergebnis;
use Ketteler\Steuern\Ustid\KettelerTaxDetector;
use Ketteler\Steuern\Ustid\UstId;
use Ketteler\Steuern\Ustid\ViesClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class UstIdTest extends TestCase
{
    public function testZerlegen(): void
    {
        self::assertSame(['kennzeichen' => 'AT', 'nummer' => 'U12345675', 'iso' => 'AT'], UstId::zerlegen('atu 1234.5675'));
        self::assertSame('GR', UstId::zerlegen('EL123456789')['iso'] ?? null);
        self::assertNull(UstId::zerlegen('CHE-123.456.789'));
        self::assertNull(UstId::zerlegen('GB123456789'));
    }

    private function vies(MockResponse $antwort, ?array &$gesendet = null): ViesClient
    {
        return new ViesClient(new MockHttpClient(function (string $m, string $u, array $o) use ($antwort, &$gesendet) {
            $gesendet = json_decode($o['body'], true);

            return $antwort;
        }), 'http://vies.test/check');
    }

    public function testGueltig(): void
    {
        $e = $this->vies(new MockResponse('{"valid":true,"name":"Testeinrichtung"}'), $gesendet)->pruefen('ATU12345675');
        self::assertSame(Ergebnis::GUELTIG, $e->status);
        self::assertSame(['countryCode' => 'AT', 'vatNumber' => 'U12345675'], $gesendet);
        self::assertSame('Testeinrichtung', $e->name);
    }

    /** @return iterable<string, array{0: MockResponse, 1: string}> */
    public static function antworten(): iterable
    {
        yield 'ungültig' => [new MockResponse('{"valid":false,"name":"---"}'), Ergebnis::UNGUELTIG];
        yield 'Mitgliedstaat nicht verfügbar' => [new MockResponse('{"actionSucceed":false,"errorWrappers":[{"error":"MS_UNAVAILABLE"}]}'), Ergebnis::NICHT_PRUEFBAR];
        yield 'Serverfehler' => [new MockResponse('kaputt', ['http_code' => 500]), Ergebnis::NICHT_PRUEFBAR];
        yield 'nicht erreichbar' => [new MockResponse('', ['error' => 'Zeitüberschreitung']), Ergebnis::NICHT_PRUEFBAR];
        yield 'unerwartete Antwort' => [new MockResponse('{"valid":"ja"}'), Ergebnis::NICHT_PRUEFBAR];
    }

    /** @dataProvider antworten */
    public function testNieStillesDurchwinken(MockResponse $antwort, string $erwartet): void
    {
        self::assertSame($erwartet, $this->vies($antwort)->pruefen('ATU12345675')->status);
    }

    public function testDeutscheNummerOhneAbfrage(): void
    {
        $vies = new ViesClient(new MockHttpClient(static fn () => throw new \LogicException('darf nicht fragen')), 'http://vies.test');
        self::assertSame(Ergebnis::INLAND, $vies->pruefen('DE123456788')->status);
        self::assertSame(Ergebnis::FORMAT, $vies->pruefen('12345')->status);
    }

    /** @return iterable<string, array{0: list<string>, 1: ?array<string, mixed>, 2: string, 3: bool}> */
    public static function befreiung(): iterable
    {
        $gueltig = ['status' => 'gueltig', 'ustid' => 'ATU12345675', 'land' => 'AT'];
        yield 'bestätigt, Lieferland AT' => [['ATU12345675'], $gueltig, 'AT', true];
        yield 'bestätigt, aber Lieferland IT' => [['ATU12345675'], $gueltig, 'IT', false];
        yield 'Nummer danach geändert' => [['ATU99999999'], $gueltig, 'AT', false];
        yield 'ungültig' => [['ATU12345675'], ['status' => 'ungueltig'] + $gueltig, 'AT', false];
        yield 'nicht prüfbar' => [['ATU12345675'], ['status' => 'nicht_pruefbar'] + $gueltig, 'AT', false];
        yield 'nie geprüft' => [['ATU12345675'], null, 'AT', false];
        yield 'deutsch' => [['DE123456788'], ['status' => 'gueltig', 'ustid' => 'DE123456788', 'land' => 'DE'], 'DE', false];
    }

    /**
     * @dataProvider befreiung
     *
     * @param list<string> $ustids
     * @param array<string, mixed>|null $pruefung
     */
    public function testSteuerfreiNurMitBestaetigung(array $ustids, ?array $pruefung, string $land, bool $erwartet): void
    {
        self::assertSame($erwartet, KettelerTaxDetector::bestaetigt($ustids, $pruefung, $land));
    }

    public function testOssSchalter(): void
    {
        foreach ([true, 'true', '1', 'ja'] as $an) {
            self::assertTrue(Steuerregeln::istAn($an));
        }
        foreach ([false, 'false', '0', '', null, 'nein'] as $aus) {
            self::assertFalse(Steuerregeln::istAn($aus));
        }
        self::assertCount(26, Steuerregeln::saetze());
    }
}
