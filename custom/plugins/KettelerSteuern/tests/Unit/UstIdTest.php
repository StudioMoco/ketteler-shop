<?php declare(strict_types=1);

namespace Ketteler\Steuern\Tests\Unit;

use Ketteler\Steuern\Steuern\Steuerregeln;
use Ketteler\Steuern\Ustid\Ergebnis;
use Ketteler\Steuern\Ustid\KettelerTaxDetector;
use Ketteler\Steuern\Ustid\UstId;
use Ketteler\Steuern\Ustid\UstIdPruefung;
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

    public function testAbfragenummerNurMitEigenerUstId(): void
    {
        $antwort = '{"valid":true,"name":"Testeinrichtung","requestDate":"2026-10-12T10:00:00.000Z","requestIdentifier":"WAPIAAAAXYZ"}';
        $e = $this->vies(new MockResponse($antwort), $gesendet)->pruefen('ATU12345675', 'DE 123 456 788');
        self::assertSame(['countryCode' => 'AT', 'vatNumber' => 'U12345675', 'requesterMemberStateCode' => 'DE', 'requesterNumber' => '123456788'], $gesendet);
        self::assertSame('WAPIAAAAXYZ', $e->alsFeld()['anfrageNummer']);
        self::assertSame('2026-10-12T10:00:00.000Z', $e->alsFeld()['viesDatum']);
        self::assertSame('DE123456788', $e->alsFeld()['anfragende']);

        $ohne = $this->vies(new MockResponse('{"valid":true}'), $gesendet)->pruefen('ATU12345675');
        self::assertArrayNotHasKey('requesterNumber', $gesendet);
        self::assertNull($ohne->alsFeld()['anfrageNummer']);
    }

    public function testVerlaufBehaeltFruehereBestaetigungen(): void
    {
        $erste = new Ergebnis(Ergebnis::GUELTIG, 'ATU12345675', 'AT', '2025-01-01T00:00:00+00:00', 'X', null, 'WAPI1');
        $stand = UstIdPruefung::neuerStand(null, $erste);
        $zweite = new Ergebnis(Ergebnis::NICHT_PRUEFBAR, 'ATU12345675', 'AT', '2026-02-01T00:00:00+00:00', null, 'VIES nicht erreichbar');
        $stand = UstIdPruefung::neuerStand($stand, $zweite);

        self::assertSame('nicht_pruefbar', $stand['status']);
        self::assertSame(['2026-02-01T00:00:00+00:00', '2025-01-01T00:00:00+00:00'], array_column($stand['verlauf'], 'geprueft'));
        self::assertSame('WAPI1', $stand['verlauf'][1]['anfrageNummer']);
        for ($i = 0; $i < 30; ++$i) {
            $stand = UstIdPruefung::neuerStand($stand, $zweite);
        }
        self::assertCount(UstIdPruefung::VERLAUF_MAX, $stand['verlauf']);
    }

    public function testNachpruefungNachEinemJahr(): void
    {
        $jetzt = new \DateTimeImmutable('2026-10-12T12:00:00+00:00');
        $gueltig = ['status' => 'gueltig', 'ustid' => 'ATU12345675', 'land' => 'AT'];
        self::assertFalse(UstIdPruefung::nachpruefungFaellig($gueltig + ['geprueft' => '2026-01-01T00:00:00+00:00'], $jetzt));
        self::assertTrue(UstIdPruefung::nachpruefungFaellig($gueltig + ['geprueft' => '2025-10-12T12:00:00+00:00'], $jetzt));
        self::assertTrue(UstIdPruefung::nachpruefungFaellig($gueltig + ['geprueft' => '2024-05-01T00:00:00+00:00'], $jetzt));
        // Nicht bestätigte Nummern werden nicht beim Kauf nachgeprüft (keine Befreiung ohnehin)
        self::assertFalse(UstIdPruefung::nachpruefungFaellig(['status' => 'ungueltig', 'geprueft' => '2024-01-01T00:00:00+00:00'], $jetzt));
        self::assertFalse(UstIdPruefung::nachpruefungFaellig(null, $jetzt));
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
