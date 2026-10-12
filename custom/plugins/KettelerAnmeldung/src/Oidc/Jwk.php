<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Oidc;

/**
 * Wandelt einen öffentlichen RSA-Schlüssel aus dem JWKS von ZITADEL (Felder n und e) in PEM um,
 * damit lcobucci/jwt die Signatur prüfen kann.
 */
final class Jwk
{
    /** @param array<string, mixed> $jwk */
    public static function rsaPem(array $jwk): string
    {
        if (($jwk['kty'] ?? null) !== 'RSA' || !isset($jwk['n'], $jwk['e'])) {
            throw new OidcFehler('Nur RSA-Schlüssel werden unterstützt.');
        }
        $n = self::ganzzahl(Pkce::base64urlDecode((string) $jwk['n']));
        $e = self::ganzzahl(Pkce::base64urlDecode((string) $jwk['e']));
        $rsaPublicKey = self::der(0x30, $n . $e);
        $algorithmus = self::der(0x30, self::der(0x06, "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01") . "\x05\x00");
        $spki = self::der(0x30, $algorithmus . self::der(0x03, "\x00" . $rsaPublicKey));

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function ganzzahl(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || \ord($bytes[0]) > 0x7F) {
            $bytes = "\x00" . $bytes;
        }

        return self::der(0x02, $bytes);
    }

    private static function der(int $typ, string $inhalt): string
    {
        $laenge = \strlen($inhalt);
        if ($laenge < 0x80) {
            return \chr($typ) . \chr($laenge) . $inhalt;
        }
        $bytes = ltrim(pack('N', $laenge), "\x00");

        return \chr($typ) . \chr(0x80 | \strlen($bytes)) . $bytes . $inhalt;
    }
}
