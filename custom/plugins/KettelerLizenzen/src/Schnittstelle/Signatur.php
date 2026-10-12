<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Schnittstelle;

/** HMAC-SHA256 über Zeit + "." + roher Körper (docs/lizenzen/shop-schnittstelle.md im Redaktions-Repository). */
final class Signatur
{
    public static function bilde(string $geheimnis, string $zeit, string $koerper): string
    {
        return 'sha256=' . hash_hmac('sha256', $zeit . '.' . $koerper, $geheimnis);
    }
}
