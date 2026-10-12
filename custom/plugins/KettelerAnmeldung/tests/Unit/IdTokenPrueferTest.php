<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Tests\Unit;

use Ketteler\Anmeldung\Oidc\IdTokenPruefer;
use Ketteler\Anmeldung\Oidc\Jwk;
use Ketteler\Anmeldung\Oidc\OidcFehler;
use Ketteler\Anmeldung\Oidc\Pkce;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use PHPUnit\Framework\TestCase;

class IdTokenPrueferTest extends TestCase
{
    private const ISS = 'http://localhost:8080';
    private const AUD = '123456789';

    private static string $privat;

    /** @var array<string, string> */
    private static array $jwk;

    public static function setUpBeforeClass(): void
    {
        $schluessel = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($schluessel, $privat);
        self::$privat = $privat;
        $d = openssl_pkey_get_details($schluessel)['rsa'];
        self::$jwk = ['kty' => 'RSA', 'kid' => 'k1', 'alg' => 'RS256', 'use' => 'sig', 'n' => Pkce::base64url($d['n']), 'e' => Pkce::base64url($d['e'])];
    }

    /** @param array<string, mixed> $claims */
    private function token(array $claims = [], ?string $privat = null, string $kid = 'k1', ?\DateTimeImmutable $ablauf = null): string
    {
        $jetzt = new \DateTimeImmutable();
        $b = (new Builder(new JoseEncoder(), ChainedFormatter::default()))
            ->issuedBy($claims['iss'] ?? self::ISS)
            ->permittedFor($claims['aud'] ?? self::AUD)
            ->relatedTo('3141592653')
            ->issuedAt($jetzt)
            ->expiresAt($ablauf ?? $jetzt->modify('+5 minutes'))
            ->withHeader('kid', $kid)
            ->withClaim('nonce', $claims['nonce'] ?? 'n-123')
            ->withClaim('email', 'Tessa@Ketteler.test')
            ->withClaim('email_verified', $claims['email_verified'] ?? true)
            ->withClaim('given_name', 'Tessa')
            ->withClaim('family_name', 'Testkundin');

        return $b->getToken(new Sha256(), InMemory::plainText($privat ?? self::$privat))->toString();
    }

    private function pruefe(string $token, string $nonce = 'n-123'): \Ketteler\Anmeldung\Oidc\Identitaet
    {
        return (new IdTokenPruefer())->pruefe($token, ['keys' => [self::$jwk]], self::ISS, self::AUD, $nonce);
    }

    public function testGueltigesToken(): void
    {
        $i = $this->pruefe($this->token());
        self::assertSame('3141592653', $i->sub);
        self::assertSame('tessa@ketteler.test', $i->email);
        self::assertTrue($i->emailBestaetigt);
        self::assertSame('Testkundin', $i->nachname);
    }

    public function testJwkErgibtGueltigenPemSchluessel(): void
    {
        self::assertNotFalse(openssl_pkey_get_public(Jwk::rsaPem(self::$jwk)));
    }

    public function testUnbestaetigteEmailWirdGemeldet(): void
    {
        self::assertFalse($this->pruefe($this->token(['email_verified' => false]))->emailBestaetigt);
    }

    /** @return iterable<string, array{0: callable(self): string, 1?: string}> */
    public static function ungueltig(): iterable
    {
        yield 'falsche nonce' => [static fn (self $t) => $t->token(['nonce' => 'anders'])];
        yield 'fremder Aussteller' => [static fn (self $t) => $t->token(['iss' => 'http://boese.test'])];
        yield 'anderer Empfänger' => [static fn (self $t) => $t->token(['aud' => '999'])];
        yield 'abgelaufen' => [static fn (self $t) => $t->token([], null, 'k1', new \DateTimeImmutable('-10 minutes'))];
        yield 'fremder Schlüssel' => [static function (self $t) {
            openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048]), $fremd);

            return $t->token([], $fremd);
        }];
        yield 'unbekannte kid' => [static fn (self $t) => $t->token([], null, 'k2')];
        yield 'verändert' => [static function (self $t) {
            [$kopf, $daten, $sig] = explode('.', $t->token());
            $claims = json_decode(Pkce::base64urlDecode($daten), true);
            $claims['sub'] = 'jemand-anderes';

            return $kopf . '.' . Pkce::base64url(json_encode($claims)) . '.' . $sig;
        }];
        yield 'kein JWT' => [static fn () => 'kein.token'];
    }

    /** @dataProvider ungueltig */
    public function testUngueltigeTokensWerdenAbgelehnt(callable $erzeuge): void
    {
        $this->expectException(OidcFehler::class);
        $this->pruefe($erzeuge($this));
    }
}
