<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Oidc;

/** Geprüfte Angaben aus dem ID-Token von ZITADEL. */
final class Identitaet
{
    public function __construct(
        public readonly string $sub,
        public readonly string $email,
        public readonly bool $emailBestaetigt,
        public readonly string $vorname,
        public readonly string $nachname,
        public readonly string $idToken,
    ) {
    }
}
