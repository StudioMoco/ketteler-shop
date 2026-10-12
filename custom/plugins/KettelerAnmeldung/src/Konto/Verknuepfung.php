<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Konto;

use Ketteler\Anmeldung\Oidc\Identitaet;
use Shopware\Core\Framework\Context;

/**
 * Findet zum ZITADEL-Konto den Shop-Kunden:
 * 1. über die ZITADEL-Kennung (sub), die am Kunden gespeichert ist;
 * 2. sonst über die E-Mail, aber nur wenn ZITADEL sie als bestätigt meldet und nur bei Kunden mit Konto (keine Gäste),
 *    die noch mit keinem anderen ZITADEL-Konto verknüpft sind; dann wird die Kennung gespeichert;
 * 3. sonst muss ein neues Kundenkonto angelegt werden.
 */
class Verknuepfung
{
    public function __construct(private readonly KundenSpeicher $speicher)
    {
    }

    public function ordneZu(Identitaet $identitaet, Context $context): Zuordnung
    {
        $id = $this->speicher->kundeMitSub($identitaet->sub, $context);
        if ($id !== null) {
            return Zuordnung::gefunden($id);
        }
        if (!$identitaet->emailBestaetigt) {
            return Zuordnung::emailUnbestaetigt();
        }

        $konten = $this->speicher->kontenMitEmail($identitaet->email, $context);
        if ($konten === []) {
            return Zuordnung::neu();
        }
        $frei = array_values(array_filter($konten, static fn (array $k): bool => $k['sub'] === null || $k['sub'] === ''));
        if (\count($frei) !== 1 || \count($konten) !== 1) {
            // Schon mit anderem ZITADEL-Konto verknüpft oder mehrdeutig: nicht raten
            return Zuordnung::konflikt();
        }
        $this->speicher->subSetzen($frei[0]['id'], $identitaet->sub, $context);

        return Zuordnung::verknuepft($frei[0]['id']);
    }
}
