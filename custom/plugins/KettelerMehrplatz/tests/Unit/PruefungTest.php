<?php declare(strict_types=1);

namespace Ketteler\Mehrplatz\Tests\Unit;

use Ketteler\Mehrplatz\Anfrage\Pruefung;
use PHPUnit\Framework\TestCase;

class PruefungTest extends TestCase
{
    /** @return array<string, string> */
    private static function gueltig(): array
    {
        return ['einrichtung' => ' Caritas Teststadt ', 'ansprechperson' => 'Petra Test', 'email' => 'Petra@Ketteler.TEST',
            'telefon' => '0221 1', 'anzahl' => '12', 'nachricht' => '', 'werk' => 'avr'];
    }

    public function testGueltigeAnfrage(): void
    {
        [$daten, $fehler] = Pruefung::pruefe(self::gueltig());
        self::assertSame([], $fehler);
        self::assertSame('Caritas Teststadt', $daten['einrichtung']);
        self::assertSame('petra@ketteler.test', $daten['email']);
        self::assertSame(12, $daten['anzahl']);
        self::assertNull($daten['nachricht']);
    }

    /** @return iterable<string, array{0: array<string, mixed>, 1: string}> */
    public static function ungueltig(): iterable
    {
        yield 'ohne Einrichtung' => [['einrichtung' => '   '], 'einrichtung'];
        yield 'ohne Telefon' => [['telefon' => ''], 'telefon'];
        yield 'E-Mail kaputt' => [['email' => 'keine-mail'], 'email'];
        yield 'Anzahl 1' => [['anzahl' => '1'], 'anzahl'];
        yield 'Anzahl keine Zahl' => [['anzahl' => 'viele'], 'anzahl'];
        yield 'Anzahl zu groß' => [['anzahl' => '100000'], 'anzahl'];
    }

    /**
     * @dataProvider ungueltig
     *
     * @param array<string, mixed> $aenderung
     */
    public function testFehler(array $aenderung, string $feld): void
    {
        [, $fehler] = Pruefung::pruefe($aenderung + self::gueltig());
        self::assertArrayHasKey($feld, $fehler);
    }

    public function testSteuerzeichenUndLaenge(): void
    {
        [$daten] = Pruefung::pruefe(['nachricht' => "Hallo\x07" . str_repeat('x', 6000)] + self::gueltig());
        self::assertStringNotContainsString("\x07", (string) $daten['nachricht']);
        self::assertSame(5000, mb_strlen((string) $daten['nachricht']));
    }
}
