<?php declare(strict_types=1);

namespace Ketteler\Lizenzen\Storefront;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/** Kundenkonto: „Meine Online-Zugänge“ mit „Jetzt lesen“. */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class OnlineZugaengeController extends StorefrontController
{
    public function __construct(private readonly MeineWerke $meineWerke)
    {
    }

    #[Route(path: '/account/online-zugaenge', name: 'frontend.ketteler.online-zugaenge', defaults: ['_loginRequired' => true, '_noStore' => true], methods: ['GET'])]
    public function anzeigen(SalesChannelContext $context): Response
    {
        $kunde = $context->getCustomer();

        return $this->renderStorefront('@KettelerLizenzen/storefront/page/account/online-zugaenge.html.twig', [
            'zugaenge' => $kunde === null || $kunde->getGuest() ? [] : $this->meineWerke->zugaenge($kunde->getId()),
        ]);
    }
}
