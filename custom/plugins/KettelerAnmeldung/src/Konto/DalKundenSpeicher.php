<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Konto;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class DalKundenSpeicher implements KundenSpeicher
{
    public const FELD_SUB = 'ketteler_zitadel_sub';

    public function __construct(private readonly EntityRepository $kunden)
    {
    }

    public function kundeMitSub(string $sub, Context $context): ?string
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('customFields.' . self::FELD_SUB, $sub))
            ->addFilter(new EqualsFilter('guest', false))
            ->setLimit(1);

        return $this->kunden->searchIds($criteria, $context)->firstId();
    }

    public function kontenMitEmail(string $email, Context $context): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('email', $email))
            ->addFilter(new EqualsFilter('guest', false))
            ->setLimit(5);
        $ergebnis = [];
        foreach ($this->kunden->search($criteria, $context) as $kunde) {
            $ergebnis[] = ['id' => $kunde->getId(), 'sub' => $kunde->getCustomFields()[self::FELD_SUB] ?? null];
        }

        return $ergebnis;
    }

    public function subSetzen(string $kundeId, string $sub, Context $context): void
    {
        $this->kunden->update([['id' => $kundeId, 'customFields' => [self::FELD_SUB => $sub]]], $context);
    }
}
