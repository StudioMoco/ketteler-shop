<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Checkout;

use Shopware\Core\Checkout\Cart\Error\Error;

/** Blockiert die Bestellung: Gäste dürfen keine Online-Artikel bestellen. Text: checkout.ketteler-online-nur-mit-konto */
class OnlineNurMitKontoFehler extends Error
{
    public const SCHLUESSEL = 'ketteler-online-nur-mit-konto';

    public function getId(): string
    {
        return self::SCHLUESSEL;
    }

    public function getMessageKey(): string
    {
        return self::SCHLUESSEL;
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getParameters(): array
    {
        return [];
    }

    public function isPersistent(): bool
    {
        return false;
    }
}
