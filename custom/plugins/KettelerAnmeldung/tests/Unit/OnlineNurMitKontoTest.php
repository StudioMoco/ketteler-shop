<?php declare(strict_types=1);

namespace Ketteler\Anmeldung\Tests\Unit;

use Ketteler\Anmeldung\Checkout\OnlineNurMitKonto;
use Ketteler\Anmeldung\Checkout\OnlineNurMitKontoFehler;
use Ketteler\Anmeldung\Storefront\AnmeldungController;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OnlineNurMitKontoTest extends TestCase
{
    private function warenkorb(string ...$arten): Cart
    {
        $cart = new Cart('t');
        foreach ($arten as $n => $art) {
            $position = new LineItem('p' . $n, LineItem::PRODUCT_LINE_ITEM_TYPE, 'p' . $n);
            $position->setPayloadValue('customFields', ['ketteler_artikelart' => $art]);
            $cart->add($position);
        }

        return $cart;
    }

    private function kontext(?bool $gast): SalesChannelContext
    {
        $kontext = $this->createMock(SalesChannelContext::class);
        $kunde = null;
        if ($gast !== null) {
            $kunde = new CustomerEntity();
            $kunde->setGuest($gast);
        }
        $kontext->method('getCustomer')->willReturn($kunde);

        return $kontext;
    }

    /** @return iterable<string, array{0: list<string>, 1: ?bool, 2: bool}> */
    public static function faelle(): iterable
    {
        yield 'Gast nur Print' => [['print', 'print'], true, false];
        yield 'Gast mit Online' => [['print', 'online'], true, true];
        yield 'Gast mit Bundle' => [['bundle'], true, true];
        yield 'Konto mit Online' => [['online'], false, false];
        yield 'nicht angemeldet' => [['online'], null, false];
    }

    /**
     * @dataProvider faelle
     *
     * @param list<string> $arten
     */
    public function testRegel(array $arten, ?bool $gast, bool $blockiert): void
    {
        $fehler = new ErrorCollection();
        (new OnlineNurMitKonto())->validate($this->warenkorb(...$arten), $fehler, $this->kontext($gast));
        self::assertSame($blockiert, $fehler->blockOrder());
        if ($blockiert) {
            self::assertInstanceOf(OnlineNurMitKontoFehler::class, $fehler->first());
        }
    }

    /** @return iterable<string, array{0: mixed, 1: ?string}> */
    public static function ziele(): iterable
    {
        yield 'Pfad' => ['/checkout/confirm', '/checkout/confirm'];
        yield 'fremde Adresse' => ['https://boese.test/', null];
        yield 'protokollrelativ' => ['//boese.test', null];
        yield 'Backslash' => ['/\\boese.test', null];
        yield 'Steuerzeichen' => ["/a\r\nLocation: x", null];
        yield 'leer' => ['', null];
        yield 'kein Text' => [['/a'], null];
    }

    /** @dataProvider ziele */
    public function testNurEigeneZiele(mixed $ziel, ?string $erwartet): void
    {
        self::assertSame($erwartet, AnmeldungController::sicheresZiel($ziel));
    }
}
