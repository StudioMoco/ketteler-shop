<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Tests\Unit;

use Ketteler\Anmeldung\Oidc\Pkce;
use PHPUnit\Framework\TestCase;

class PkceTest extends TestCase
{
    public function testVerifierErfuelltRfc7636(): void
    {
        $v = Pkce::verifier();
        self::assertMatchesRegularExpression('/^[A-Za-z0-9\-._~]{43,128}$/', $v);
        self::assertNotSame($v, Pkce::verifier());
    }

    public function testChallengeIstS256(): void
    {
        // Beispiel aus RFC 7636, Anhang B
        self::assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', Pkce::challenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));
    }

    public function testBase64urlHinUndZurueck(): void
    {
        $daten = random_bytes(37);
        self::assertSame($daten, Pkce::base64urlDecode(Pkce::base64url($daten)));
    }
}
