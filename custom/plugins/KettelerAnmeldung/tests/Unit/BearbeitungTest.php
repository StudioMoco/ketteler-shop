<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Tests\Unit;

use Ketteler\Anmeldung\Widerruf\Bearbeitung;
use PHPUnit\Framework\TestCase;

class BearbeitungTest extends TestCase
{
    public function testMarkierenSpeichertZeitBearbeiterUndVerlauf(): void
    {
        $zeit = new \DateTimeImmutable('2026-10-12T10:00:00+00:00');
        $stand = Bearbeitung::umschalten(null, true, $zeit, 'Erika Test (erika)', 'u1');

        self::assertTrue($stand['bearbeitet']);
        self::assertSame('2026-10-12T10:00:00+00:00', $stand['zeit']);
        self::assertSame('Erika Test (erika)', $stand['benutzer']);
        self::assertSame('u1', $stand['benutzerId']);
        self::assertCount(1, $stand['verlauf']);
        self::assertSame('bearbeitet', $stand['verlauf'][0]['aktion']);
    }

    public function testZuruecknehmenBehaeltDenVerlauf(): void
    {
        $erst = Bearbeitung::umschalten(null, true, new \DateTimeImmutable('2026-10-12T10:00:00+00:00'), 'A', 'a');
        $zurueck = Bearbeitung::umschalten($erst, false, new \DateTimeImmutable('2026-10-12T11:00:00+00:00'), 'B', 'b');

        self::assertFalse($zurueck['bearbeitet']);
        self::assertNull($zurueck['zeit']);
        self::assertNull($zurueck['benutzer']);
        self::assertSame(['bearbeitet', 'zurueckgenommen'], array_column($zurueck['verlauf'], 'aktion'));
        self::assertSame(['A', 'B'], array_column($zurueck['verlauf'], 'benutzer'));
    }

    public function testDoppeltesMarkierenWirdAbgelehnt(): void
    {
        $erst = Bearbeitung::umschalten(null, true, new \DateTimeImmutable(), 'A', 'a');
        $this->expectException(\DomainException::class);
        Bearbeitung::umschalten($erst, true, new \DateTimeImmutable(), 'A', 'a');
    }

    public function testZuruecknehmenOhneMarkierungWirdAbgelehnt(): void
    {
        $this->expectException(\DomainException::class);
        Bearbeitung::umschalten(null, false, new \DateTimeImmutable(), 'A', 'a');
    }

    public function testNeuerWiderrufOeffnetBearbeiteteBestellungWieder(): void
    {
        self::assertNull(Bearbeitung::neuerEingang(null, new \DateTimeImmutable()));
        $erst = Bearbeitung::umschalten(null, true, new \DateTimeImmutable('2026-10-12T10:00:00+00:00'), 'A', 'a');
        $offen = Bearbeitung::neuerEingang($erst, new \DateTimeImmutable('2026-10-13T10:00:00+00:00'));

        self::assertNotNull($offen);
        self::assertFalse($offen['bearbeitet']);
        self::assertSame(['bearbeitet', 'neuer_widerruf'], array_column($offen['verlauf'], 'aktion'));
        self::assertNull(Bearbeitung::neuerEingang($offen, new \DateTimeImmutable()));
    }
}
