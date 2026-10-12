<?php declare(strict_types=1);

namespace Ketteler\Steuern\Ustid;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\AbstractTaxDetector;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Wie der TaxDetector von Shopware, aber steuerfrei für Firmenkunden nur mit einer über VIES bestätigten USt-IdNr.
 * eines anderen EU-Landes, und nur wenn deren Land das Lieferland ist. Shopware selbst prüft nur das Format.
 */
class KettelerTaxDetector extends AbstractTaxDetector
{
    public function __construct(private readonly AbstractTaxDetector $inner)
    {
    }

    public function getDecorated(): AbstractTaxDetector
    {
        return $this->inner;
    }

    public function useGross(SalesChannelContext $context): bool
    {
        return $this->inner->useGross($context);
    }

    public function isNetDelivery(SalesChannelContext $context): bool
    {
        $land = $context->getShippingLocation()->getCountry();

        return $land->getCustomerTax()->getEnabled() || $this->isCompanyTaxFree($context, $land);
    }

    public function getTaxState(SalesChannelContext $context): string
    {
        if ($this->isNetDelivery($context)) {
            return CartPrice::TAX_STATE_FREE;
        }

        return $this->useGross($context) ? CartPrice::TAX_STATE_GROSS : CartPrice::TAX_STATE_NET;
    }

    public function isCompanyTaxFree(SalesChannelContext $context, CountryEntity $shippingLocationCountry): bool
    {
        if (!$this->inner->isCompanyTaxFree($context, $shippingLocationCountry)) {
            return false;
        }

        return self::bestaetigt($context->getCustomer()?->getVatIds() ?? [], $context->getCustomer()?->getCustomFields()[UstIdPruefung::FELD] ?? null, (string) $shippingLocationCountry->getIso());
    }

    /**
     * @param array<int, string> $ustids
     * @param array<string, mixed>|null $pruefung
     */
    public static function bestaetigt(array $ustids, ?array $pruefung, string $lieferland): bool
    {
        $ustid = array_values(array_filter($ustids))[0] ?? null;

        return \is_string($ustid)
            && \is_array($pruefung)
            && ($pruefung['status'] ?? null) === Ergebnis::GUELTIG
            && ($pruefung['ustid'] ?? null) === UstId::normalisieren($ustid)
            && ($pruefung['land'] ?? null) === $lieferland
            && $lieferland !== 'DE';
    }
}
