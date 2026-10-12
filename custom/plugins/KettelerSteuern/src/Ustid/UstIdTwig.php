<?php declare(strict_types=1);

namespace Ketteler\Steuern\Ustid;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Stand der USt-IdNr.-Prüfung für Hinweise im Kundenkonto und in der Kasse (null = keine Nummer). */
class UstIdTwig extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [new TwigFunction('ketteler_ustid', static function (?SalesChannelContext $context): ?array {
            $kunde = $context?->getCustomer();
            $ustid = array_values(array_filter($kunde?->getVatIds() ?? []))[0] ?? null;
            if ($kunde === null || !\is_string($ustid) || $ustid === '') {
                return null;
            }
            $pruefung = $kunde->getCustomFields()[UstIdPruefung::FELD] ?? null;

            return \is_array($pruefung) ? $pruefung + ['ustid' => $ustid] : ['status' => Ergebnis::NICHT_PRUEFBAR, 'ustid' => $ustid];
        })];
    }
}
