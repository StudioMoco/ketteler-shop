<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Tests\Unit;

use Ketteler\Anmeldung\Checkout\Widerruf;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class WiderrufTest extends TestCase
{
    private function warenkorb(string $art): Cart
    {
        $cart = new Cart('t');
        $position = new LineItem('p', LineItem::PRODUCT_LINE_ITEM_TYPE, 'p');
        $position->setPayloadValue('customFields', ['ketteler_artikelart' => $art]);
        $cart->add($position);

        return $cart;
    }

    private function kontext(?string $kontotyp): SalesChannelContext
    {
        $kontext = $this->createMock(SalesChannelContext::class);
        $kunde = null;
        if ($kontotyp !== null) {
            $kunde = new CustomerEntity();
            $kunde->setAccountType($kontotyp);
        }
        $kontext->method('getCustomer')->willReturn($kunde);

        return $kontext;
    }

    /** @return iterable<string, array{0: string, 1: ?string, 2: bool}> */
    public static function faelle(): iterable
    {
        yield 'Privat mit Online' => ['online', CustomerEntity::ACCOUNT_TYPE_PRIVATE, true];
        yield 'Privat mit Bundle' => ['bundle', CustomerEntity::ACCOUNT_TYPE_PRIVATE, true];
        yield 'Privat nur Print' => ['print', CustomerEntity::ACCOUNT_TYPE_PRIVATE, false];
        yield 'Firma mit Online' => ['online', CustomerEntity::ACCOUNT_TYPE_BUSINESS, false];
        yield 'nicht angemeldet' => ['online', null, false];
    }

    /** @dataProvider faelle */
    public function testHaekchenNoetig(string $art, ?string $kontotyp, bool $erwartet): void
    {
        self::assertSame($erwartet, Widerruf::noetig($this->warenkorb($art), $this->kontext($kontotyp)));
    }
}
