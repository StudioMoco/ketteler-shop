<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Widerruf;

use Ketteler\Anmeldung\Checkout\OnlineArtikel;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Positionen einer eigenen Bestellung für das Widerrufsformular (nur angemeldete Kunden, nur ihre Bestellungen). */
class WiderrufTwig extends AbstractExtension
{
    public function __construct(private readonly EntityRepository $bestellungen)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('ketteler_widerruf_positionen', $this->positionen(...))];
    }

    /** @return list<array{id: string, label: string, nummer: string, online: bool}> */
    public function positionen(mixed $nummer, ?SalesChannelContext $context): array
    {
        $kunde = $context?->getCustomer();
        if (!\is_string($nummer) || trim($nummer) === '' || $kunde === null) {
            return [];
        }
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('orderNumber', trim($nummer)))
            ->addFilter(new EqualsFilter('orderCustomer.customerId', $kunde->getId()))
            ->addAssociation('lineItems')->setLimit(1);
        $bestellung = $this->bestellungen->search($criteria, $context->getContext())->first();
        if (!$bestellung instanceof OrderEntity) {
            return [];
        }
        $ergebnis = [];
        foreach ($bestellung->getLineItems() ?? [] as $p) {
            if ($p->getType() !== 'product') {
                continue;
            }
            $felder = $p->getPayload()['customFields'] ?? [];
            $ergebnis[] = [
                'id' => $p->getId(),
                'label' => $p->getLabel(),
                'nummer' => (string) ($p->getPayload()['productNumber'] ?? ''),
                'online' => \is_array($felder) && \in_array($felder['ketteler_artikelart'] ?? null, OnlineArtikel::ARTEN, true),
            ];
        }

        return $ergebnis;
    }
}
