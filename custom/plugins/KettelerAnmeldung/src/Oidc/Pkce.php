<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Oidc;

/**
 * Zufallswerte für state, nonce und PKCE (RFC 7636, Verfahren S256), alles base64url ohne Auffüllung.
 */
final class Pkce
{
    public static function zufall(int $bytes = 32): string
    {
        return self::base64url(random_bytes($bytes));
    }

    /** 64 Zeichen, erlaubt sind 43 bis 128. */
    public static function verifier(): string
    {
        return self::zufall(48);
    }

    public static function challenge(string $verifier): string
    {
        return self::base64url(hash('sha256', $verifier, true));
    }

    public static function base64url(string $daten): string
    {
        return rtrim(strtr(base64_encode($daten), '+/', '-_'), '=');
    }

    public static function base64urlDecode(string $text): string
    {
        $ergebnis = base64_decode(strtr($text, '-_', '+/') . str_repeat('=', (4 - \strlen($text) % 4) % 4), true);
        if ($ergebnis === false) {
            throw new OidcFehler('Ungültige base64url-Daten.');
        }

        return $ergebnis;
    }
}
