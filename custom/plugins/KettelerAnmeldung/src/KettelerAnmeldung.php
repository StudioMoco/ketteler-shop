<?php declare(strict_types=1);

namespace Ketteler\Anmeldung;

use Ketteler\Anmeldung\Konto\DalKundenSpeicher;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class KettelerAnmeldung extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        $this->zusatzfelder($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->zusatzfelder($updateContext->getContext());
    }

    /** ZITADEL-Kennung (sub) am Kunden; nur intern, Kunden können es nicht ändern. */
    private function zusatzfelder(Context $context): void
    {
        $this->container->get('custom_field_set.repository')->upsert([[
            'id' => Uuid::fromStringToHex('ketteler.zusatzfelder.kunde'),
            'name' => 'ketteler_kunde',
            'config' => ['label' => ['de-DE' => 'Ketteler', 'en-GB' => 'Ketteler']],
            'relations' => [['id' => Uuid::fromStringToHex('ketteler.zusatzfelder.kunde.customer'), 'entityName' => 'customer']],
            'customFields' => [[
                'id' => Uuid::fromStringToHex('ketteler.zusatzfeld.zitadel-sub'),
                'name' => DalKundenSpeicher::FELD_SUB,
                'type' => CustomFieldTypes::TEXT,
                'allowCustomerWrite' => false,
                'config' => [
                    'label' => ['de-DE' => 'ZITADEL-Kennung', 'en-GB' => 'ZITADEL subject'],
                    'helpText' => ['de-DE' => 'Verknüpft das Kundenkonto mit dem Ketteler-Konto (ZITADEL). Nicht von Hand ändern.'],
                    'componentName' => 'sw-field',
                    'customFieldType' => 'text',
                    'customFieldPosition' => 1,
                ],
            ]],
        ]], $context);

        // An der Bestellung: Eingang von Widerrufen (lesbar für die Verwaltung)
        $this->container->get('custom_field_set.repository')->upsert([[
            'id' => Uuid::fromStringToHex('ketteler.zusatzfelder.bestellung'),
            'name' => 'ketteler_bestellung',
            'config' => ['label' => ['de-DE' => 'Ketteler', 'en-GB' => 'Ketteler']],
            'relations' => [['id' => Uuid::fromStringToHex('ketteler.zusatzfelder.bestellung.order'), 'entityName' => 'order']],
            'customFields' => [[
                'id' => Uuid::fromStringToHex('ketteler.zusatzfeld.widerruf-eingang'),
                'name' => 'ketteler_widerruf_eingang',
                'type' => CustomFieldTypes::TEXT,
                'allowCustomerWrite' => false,
                'config' => [
                    'label' => ['de-DE' => 'Widerruf eingegangen (Widerrufsfunktion)', 'en-GB' => 'Revocation received'],
                    'helpText' => ['de-DE' => 'Wird beim Eingang eines Widerrufs über das Formular im Shop gefüllt: Zeitpunkt, Positionen, Kommentar. Die Lizenz wird nicht automatisch beendet.'],
                    'componentName' => 'sw-field',
                    'customFieldType' => 'textarea',
                    'type' => 'textarea',
                    'customFieldPosition' => 1,
                ],
            ]],
        ]], $context);
    }
}
