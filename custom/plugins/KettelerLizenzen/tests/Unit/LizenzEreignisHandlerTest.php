<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Tests\Unit;

use Ketteler\Lizenzen\Nachricht\LizenzEreignis;
use Ketteler\Lizenzen\Nachricht\LizenzEreignisHandler;
use Ketteler\Lizenzen\Nachricht\Meldung;
use Ketteler\Lizenzen\Nachricht\Position;
use Ketteler\Lizenzen\Nachricht\PositionenSpeicher;
use Ketteler\Lizenzen\Schnittstelle\LizenzSchnittstelle;
use Ketteler\Lizenzen\Subscriber\EndgueltigGescheitertSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

class LizenzEreignisHandlerTest extends TestCase
{
    private object $speicher;

    private object $meldung;

    /** @var list<array<string, mixed>> */
    private array $anfragen = [];

    /** @param array<string, mixed> $lizenz */
    private function position(array $lizenz = [], bool $storniert = false, int $menge = 1): void
    {
        $this->speicher = new class(new Position('o1', 'p1', 'SW100007', '2026-10-12', 'kunde@ketteler.test', 'Kara Kunde', 'avr', $menge, $storniert, $lizenz)) implements PositionenSpeicher {
            public function __construct(public Position $position)
            {
            }

            public function laden(string $orderId, string $positionId): ?Position
            {
                return $this->position;
            }

            public function speichern(string $positionId, array $lizenz): void
            {
                $p = $this->position;
                $this->position = new Position($p->orderId, $p->positionId, $p->bestellnummer, $p->bestelldatum, $p->email, $p->name, $p->produkt, $p->menge, $p->bestellungStorniert, array_filter(array_merge($p->lizenz, $lizenz), static fn ($w) => $w !== null));
            }
        };
        $this->meldung = new class implements Meldung {
            /** @var list<string> */
            public array $anlaesse = [];

            public function melden(Position $position, string $anlass, string $text): void
            {
                $this->anlaesse[] = $anlass;
            }
        };
    }

    /** @param list<MockResponse> $antworten */
    private function handler(array $antworten): LizenzEreignisHandler
    {
        $http = new MockHttpClient(function (string $m, string $u, array $o) use (&$antworten) {
            $this->anfragen[] = json_decode($o['body'], true);

            return array_shift($antworten) ?? new MockResponse('', ['http_code' => 500]);
        });

        return new LizenzEreignisHandler($this->speicher, new LizenzSchnittstelle($http, 'http://a.test/x', 'g'), $this->meldung, new NullLogger());
    }

    private function lizenz(): array
    {
        return $this->speicher->position->lizenz;
    }

    public function testAnlegenSendetEreignisUndMerktLizenz(): void
    {
        $this->position(['status' => 'offen']);
        ($this->handler([new MockResponse('{"lizenz_id":9,"status":"aktiv"}', ['http_code' => 201])]))(new LizenzEreignis('o1', 'p1', LizenzEreignis::ANLEGEN));

        self::assertSame([[
            'ereignis_id' => 'order-o1-p1-lizenz', 'art' => 'lizenz_anlegen', 'email' => 'kunde@ketteler.test', 'name' => 'Kara Kunde',
            'produkt' => 'avr', 'typ' => 'einzel', 'beginn' => '2026-10-12', 'bestellnummer' => 'SW100007',
        ]], $this->anfragen);
        self::assertSame('angelegt', $this->lizenz()['status']);
        self::assertSame(9, $this->lizenz()['lizenz_id']);
        self::assertSame([], $this->meldung->anlaesse);
    }

    public function testDoppelteZustellungSendetNichtErneut(): void
    {
        $this->position(['status' => 'angelegt', 'lizenz_id' => 9]);
        ($this->handler([]))(new LizenzEreignis('o1', 'p1', LizenzEreignis::ANLEGEN));
        self::assertSame([], $this->anfragen);
        self::assertSame(9, $this->lizenz()['lizenz_id']);
    }

    public function testWiederholungDerSchnittstelleZaehltAlsErfolg(): void
    {
        // Absturz nach dem Senden, vor dem Speichern: zweite Zustellung bekommt "wiederholt"
        $this->position(['status' => 'offen']);
        ($this->handler([new MockResponse('{"lizenz_id":9,"status":"aktiv","wiederholt":true}', ['http_code' => 200])]))(new LizenzEreignis('o1', 'p1', LizenzEreignis::ANLEGEN));
        self::assertSame('angelegt', $this->lizenz()['status']);
    }

    public function testAusfallWirdWiederholt(): void
    {
        $this->position(['status' => 'offen']);
        try {
            ($this->handler([new MockResponse('', ['http_code' => 503])]))(new LizenzEreignis('o1', 'p1', LizenzEreignis::ANLEGEN));
            self::fail('Wiederholung erwartet');
        } catch (RecoverableMessageHandlingException) {
        }
        self::assertSame('wartet', $this->lizenz()['status']);
        self::assertSame(1, $this->lizenz()['versuche']);
        self::assertSame([], $this->meldung->anlaesse);
    }

    public function testAblehnungWirdGemeldetUndNichtWiederholt(): void
    {
        $this->position(['status' => 'offen']);
        ($this->handler([new MockResponse('{"message":"E-Mail passt nicht"}', ['http_code' => 422])]))(new LizenzEreignis('o1', 'p1', LizenzEreignis::ANLEGEN));
        self::assertSame('fehler', $this->lizenz()['status']);
        self::assertSame(['Lizenz nicht angelegt'], $this->meldung->anlaesse);
    }

    public function testStornierteBestellungBekommtKeineLizenz(): void
    {
        $this->position(['status' => 'wartet'], true);
        ($this->handler([]))(new LizenzEreignis('o1', 'p1', LizenzEreignis::ANLEGEN));
        self::assertSame([], $this->anfragen);
        self::assertSame('storniert', $this->lizenz()['status']);
    }

    public function testMengeGroesserEinsGehtAnDieVerwaltung(): void
    {
        $this->position(['status' => 'offen'], false, 3);
        ($this->handler([new MockResponse('{"lizenz_id":9}', ['http_code' => 201])]))(new LizenzEreignis('o1', 'p1', LizenzEreignis::ANLEGEN));
        self::assertSame('angelegt', $this->lizenz()['status']);
        self::assertSame(['Mehrplatz'], $this->meldung->anlaesse);
    }

    public function testStornoBeendetAngelegteLizenz(): void
    {
        $this->position(['status' => 'angelegt', 'lizenz_id' => 9]);
        ($this->handler([new MockResponse('{"lizenz_id":9,"status":"beendet","ende":"2026-10-12"}', ['http_code' => 200])]))(new LizenzEreignis('o1', 'p1', LizenzEreignis::STORNIEREN));
        self::assertSame('stornieren', $this->anfragen[0]['art']);
        self::assertSame('order-o1-p1-storno', $this->anfragen[0]['ereignis_id']);
        self::assertSame('storniert', $this->lizenz()['status']);
        self::assertSame('2026-10-12', $this->lizenz()['ende']);
    }

    public function testStornoOhneLizenzSendetNichts(): void
    {
        $this->position(['status' => 'wartet']);
        ($this->handler([]))(new LizenzEreignis('o1', 'p1', LizenzEreignis::STORNIEREN));
        self::assertSame([], $this->anfragen);
        self::assertSame('storniert', $this->lizenz()['status']);
    }

    public function testStornoUnbekannteLizenz404(): void
    {
        $this->position(['status' => 'angelegt']);
        ($this->handler([new MockResponse('{"message":"keine"}', ['http_code' => 404])]))(new LizenzEreignis('o1', 'p1', LizenzEreignis::STORNIEREN));
        self::assertSame('storniert', $this->lizenz()['status']);
        self::assertArrayHasKey('hinweis', $this->lizenz());
    }

    public function testNachDemLetztenVersuchFehlerUndMeldung(): void
    {
        $this->position(['status' => 'wartet', 'versuche' => 27]);
        $nachricht = new LizenzEreignis('o1', 'p1', LizenzEreignis::ANLEGEN);
        $subscriber = new EndgueltigGescheitertSubscriber($this->speicher, $this->meldung);

        $subscriber->gescheitert(new WorkerMessageFailedEvent(new Envelope($nachricht), 'ketteler_lizenz', new \RuntimeException('503')));
        self::assertSame('fehler', $this->lizenz()['status']);
        self::assertSame(['Lizenz nicht angelegt'], $this->meldung->anlaesse);
    }

    public function testVorDemLetztenVersuchKeineMeldung(): void
    {
        $this->position(['status' => 'wartet']);
        $ereignis = new WorkerMessageFailedEvent(new Envelope(new LizenzEreignis('o1', 'p1', LizenzEreignis::ANLEGEN)), 'ketteler_lizenz', new \RuntimeException('503'));
        $ereignis->setForRetry();
        (new EndgueltigGescheitertSubscriber($this->speicher, $this->meldung))->gescheitert($ereignis);
        self::assertSame('wartet', $this->lizenz()['status']);
        self::assertSame([], $this->meldung->anlaesse);
    }
}
