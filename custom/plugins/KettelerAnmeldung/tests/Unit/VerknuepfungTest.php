<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Tests\Unit;

use Ketteler\Anmeldung\Konto\KundenSpeicher;
use Ketteler\Anmeldung\Konto\Verknuepfung;
use Ketteler\Anmeldung\Konto\Zuordnung;
use Ketteler\Anmeldung\Oidc\Identitaet;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

class VerknuepfungTest extends TestCase
{
    /**
     * @param array<string, string> $mitSub Kunde-ID => sub
     * @param list<array{id: string, sub: ?string}> $mitEmail
     */
    private function speicher(array $mitSub = [], array $mitEmail = []): KundenSpeicher
    {
        return new class($mitSub, $mitEmail) implements KundenSpeicher {
            /** @var array<string, string> */
            public array $gesetzt = [];

            public function __construct(private readonly array $mitSub, private readonly array $mitEmail)
            {
            }

            public function kundeMitSub(string $sub, Context $context): ?string
            {
                return array_search($sub, $this->mitSub, true) ?: null;
            }

            public function kontenMitEmail(string $email, Context $context): array
            {
                return $this->mitEmail;
            }

            public function subSetzen(string $kundeId, string $sub, Context $context): void
            {
                $this->gesetzt[$kundeId] = $sub;
            }
        };
    }

    private static function identitaet(bool $bestaetigt = true): Identitaet
    {
        return new Identitaet('sub-1', 'kunde@ketteler.test', $bestaetigt, 'K', 'D', 'token');
    }

    public function testZuerstUeberDieKennung(): void
    {
        $s = $this->speicher(['kunde-a' => 'sub-1'], [['id' => 'kunde-b', 'sub' => null]]);
        $z = (new Verknuepfung($s))->ordneZu(self::identitaet(false), Context::createDefaultContext());
        self::assertSame(Zuordnung::GEFUNDEN, $z->art);
        self::assertSame('kunde-a', $z->kundeId);
        self::assertSame([], $s->gesetzt);
    }

    public function testBestaetigteEmailVerknuepftBestandskunden(): void
    {
        $s = $this->speicher([], [['id' => 'kunde-b', 'sub' => null]]);
        $z = (new Verknuepfung($s))->ordneZu(self::identitaet(), Context::createDefaultContext());
        self::assertSame(Zuordnung::VERKNUEPFT, $z->art);
        self::assertSame(['kunde-b' => 'sub-1'], $s->gesetzt);
    }

    public function testUnbestaetigteEmailVerknuepftNie(): void
    {
        $s = $this->speicher([], [['id' => 'kunde-b', 'sub' => null]]);
        $z = (new Verknuepfung($s))->ordneZu(self::identitaet(false), Context::createDefaultContext());
        self::assertSame(Zuordnung::EMAIL_UNBESTAETIGT, $z->art);
        self::assertFalse($z->kannAnmelden());
        self::assertSame([], $s->gesetzt);
    }

    public function testEmailMitAndererKennungIstKonflikt(): void
    {
        $s = $this->speicher([], [['id' => 'kunde-b', 'sub' => 'sub-anders']]);
        $z = (new Verknuepfung($s))->ordneZu(self::identitaet(), Context::createDefaultContext());
        self::assertSame(Zuordnung::KONFLIKT, $z->art);
        self::assertSame([], $s->gesetzt);
    }

    public function testMehrdeutigeEmailIstKonflikt(): void
    {
        $s = $this->speicher([], [['id' => 'a', 'sub' => null], ['id' => 'b', 'sub' => null]]);
        self::assertSame(Zuordnung::KONFLIKT, (new Verknuepfung($s))->ordneZu(self::identitaet(), Context::createDefaultContext())->art);
    }

    public function testUnbekanntIstNeu(): void
    {
        self::assertSame(Zuordnung::NEU, (new Verknuepfung($this->speicher()))->ordneZu(self::identitaet(), Context::createDefaultContext())->art);
    }
}
