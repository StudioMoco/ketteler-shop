<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Konto;

use Shopware\Core\Framework\Context;

/** Zugriff auf Shop-Kunden, so schmal, dass die Verknüpfung ohne Datenbank testbar ist. */
interface KundenSpeicher
{
    public function kundeMitSub(string $sub, Context $context): ?string;

    /**
     * Kunden mit Konto (keine Gäste) zu dieser E-Mail.
     *
     * @return list<array{id: string, sub: ?string}>
     */
    public function kontenMitEmail(string $email, Context $context): array;

    public function subSetzen(string $kundeId, string $sub, Context $context): void;
}
