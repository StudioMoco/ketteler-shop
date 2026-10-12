<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Tests\Unit;

use Ketteler\Anmeldung\Widerruf\Auswertung;
use PHPUnit\Framework\TestCase;

class AuswertungTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function bestellung(bool $zustimmung = false): array
    {
        return ['id' => 'o1', 'nummer' => 'SW100001', 'email' => 'Kunde@Ketteler.test', 'kundeId' => 'k1',
            'zustimmung' => $zustimmung ? ['zugestimmt' => '2026-10-12T10:00:00+00:00', 'text' => 'x'] : null,
            'positionen' => [
                ['id' => 'p1', 'label' => 'Buch', 'nummer' => 'TEST-P-001', 'online' => false],
                ['id' => 'p2', 'label' => 'Zugang', 'nummer' => 'TEST-O-EK', 'online' => true],
            ]];
    }

    public function testZuordnungUeberEmail(): void
    {
        $a = Auswertung::auswerten(self::bestellung(), 'SW100001', 'kunde@ketteler.test', null, ['p1']);
        self::assertTrue($a->zugeordnet);
        self::assertSame('Buch (TEST-P-001)', $a->umfangFuerKunde());
        self::assertFalse($a->onlineMitZustimmung());
    }

    public function testFremdeEmailNichtZugeordnet(): void
    {
        $a = Auswertung::auswerten(self::bestellung(), 'SW100001', 'fremd@ketteler.test', null, ['p1']);
        self::assertFalse($a->zugeordnet);
        self::assertStringStartsWith('Nicht zugeordnet, bitte von Hand prüfen', $a->hinweisVerwaltung());
        self::assertSame('die gesamte Bestellung bzw. die in Ihrer Nachricht genannten Artikel', $a->umfangFuerKunde());
    }

    public function testUnbekannteNummer(): void
    {
        self::assertStringStartsWith('Nicht zugeordnet, bitte von Hand prüfen', Auswertung::auswerten(null, 'X', 'a@b.test', null, [])->hinweisVerwaltung());
    }

    public function testOnlineMitZustimmungWirdMarkiert(): void
    {
        $a = Auswertung::auswerten(self::bestellung(true), 'SW100001', '', 'k1', []);
        self::assertTrue($a->zugeordnet && $a->alle && $a->onlineMitZustimmung());
        self::assertStringContainsString('Widerrufsrecht kann damit erloschen sein', $a->hinweisVerwaltung());
    }

    public function testFremdePositionsIdsWerdenIgnoriert(): void
    {
        $a = Auswertung::auswerten(self::bestellung(), 'SW100001', 'kunde@ketteler.test', null, ['fremd']);
        self::assertTrue($a->alle);
        self::assertCount(2, $a->positionen);
    }
}
