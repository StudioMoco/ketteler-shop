<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Konto;

use Ketteler\Anmeldung\Oidc\Identitaet;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Legt beim ersten Anmelden das Shop-Kundenkonto an. Name und E-Mail kommen aus ZITADEL, die Rechnungsanschrift
 * gibt der Kunde ein. Das Shopware-Passwort ist ein Zufallswert, den niemand kennt; angemeldet wird nur über ZITADEL.
 * Shopware meldet den neuen Kunden nach der Registrierung selbst an.
 */
class KontoAnlage
{
    public function __construct(
        private readonly AbstractRegisterRoute $registrierung,
        private readonly KundenSpeicher $speicher,
        private readonly EntityRepository $anreden,
        private readonly EntityRepository $laender,
    ) {
    }

    /** @param array<string, mixed> $eingabe Formularfelder: kontotyp, firma, ustid, strasse, plz, ort, land (ID) */
    public function anlegen(Identitaet $identitaet, array $eingabe, SalesChannelContext $context): string
    {
        $firma = trim((string) ($eingabe['firma'] ?? ''));
        $geschaeftlich = ($eingabe['kontotyp'] ?? 'privat') === 'firma';
        $anschrift = [
            'street' => trim((string) ($eingabe['strasse'] ?? '')),
            'zipcode' => trim((string) ($eingabe['plz'] ?? '')),
            'city' => trim((string) ($eingabe['ort'] ?? '')),
            'countryId' => $this->land((string) ($eingabe['land'] ?? ''), $context),
        ];
        if ($geschaeftlich) {
            $anschrift['company'] = $firma;
        }

        $daten = new RequestDataBag([
            'guest' => false,
            'email' => $identitaet->email,
            'password' => bin2hex(random_bytes(32)),
            'salutationId' => $this->anredeOhneAngabe($context),
            'firstName' => $identitaet->vorname !== '' ? $identitaet->vorname : 'Kundin/Kunde',
            'lastName' => $identitaet->nachname !== '' ? $identitaet->nachname : $identitaet->email,
            'accountType' => $geschaeftlich ? 'business' : 'private',
            'company' => $geschaeftlich ? $firma : null,
            'vatIds' => $geschaeftlich && trim((string) ($eingabe['ustid'] ?? '')) !== '' ? [trim((string) $eingabe['ustid'])] : null,
            'acceptedDataProtection' => true,
            'billingAddress' => new RequestDataBag($anschrift),
        ]);

        $antwort = $this->registrierung->register($daten, $context, false);
        $kundeId = $antwort->getCustomer()->getId();
        $this->speicher->subSetzen($kundeId, $identitaet->sub, $context->getContext());

        return $kundeId;
    }

    private function anredeOhneAngabe(SalesChannelContext $context): ?string
    {
        return $this->anreden->searchIds((new Criteria())->addFilter(new EqualsFilter('salutationKey', 'not_specified'))->setLimit(1), $context->getContext())->firstId();
    }

    /** Gewähltes Lieferland, wenn es zum Verkaufskanal gehört; sonst Deutschland. */
    private function land(string $id, SalesChannelContext $context): ?string
    {
        if ($id !== '' && \in_array($id, $this->lieferlaender($context), true)) {
            return $id;
        }

        return $this->laender->searchIds((new Criteria())->addFilter(new EqualsFilter('iso', 'DE'))->setLimit(1), $context->getContext())->firstId();
    }

    /** @return list<string> IDs der Lieferländer des Verkaufskanals */
    public function lieferlaender(SalesChannelContext $context): array
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('salesChannels.id', $context->getSalesChannelId()))->addFilter(new EqualsFilter('active', true));

        return array_values($this->laender->searchIds($criteria, $context->getContext())->getIds());
    }

    /** @return array<string, string> ID => Name, Deutschland zuerst */
    public function lieferlaenderMitNamen(SalesChannelContext $context): array
    {
        $criteria = (new Criteria($this->lieferlaender($context)))->addSorting(new FieldSorting('position'), new FieldSorting('name'));
        $namen = [];
        foreach ($this->laender->search($criteria, $context->getContext()) as $land) {
            $namen[$land->getId()] = (string) $land->getTranslation('name');
        }

        return $namen;
    }
}
