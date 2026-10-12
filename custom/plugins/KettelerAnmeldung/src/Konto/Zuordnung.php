<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Konto;

/** Ergebnis der Verknüpfung. */
final class Zuordnung
{
    public const GEFUNDEN = 'gefunden';
    public const VERKNUEPFT = 'verknuepft';
    public const NEU = 'neu';
    public const EMAIL_UNBESTAETIGT = 'email_unbestaetigt';
    public const KONFLIKT = 'konflikt';

    private function __construct(public readonly string $art, public readonly ?string $kundeId = null)
    {
    }

    public static function gefunden(string $id): self
    {
        return new self(self::GEFUNDEN, $id);
    }

    public static function verknuepft(string $id): self
    {
        return new self(self::VERKNUEPFT, $id);
    }

    public static function neu(): self
    {
        return new self(self::NEU);
    }

    public static function emailUnbestaetigt(): self
    {
        return new self(self::EMAIL_UNBESTAETIGT);
    }

    public static function konflikt(): self
    {
        return new self(self::KONFLIKT);
    }

    public function kannAnmelden(): bool
    {
        return $this->kundeId !== null;
    }
}
