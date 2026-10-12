<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Storefront;

use Ketteler\Anmeldung\Checkout\Widerruf;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class WiderrufTwig extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [new TwigFunction('ketteler_widerruf_noetig', Widerruf::noetig(...))];
    }
}
