<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Oidc;

/** Fehler im Anmeldeablauf. Die Meldung ist für das Protokoll, nicht für Kunden. */
class OidcFehler extends \RuntimeException
{
}
