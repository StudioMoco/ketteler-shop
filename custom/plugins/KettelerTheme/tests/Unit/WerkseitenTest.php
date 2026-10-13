<?php declare(strict_types=1);

namespace Ketteler\Theme\Tests\Unit;

use Ketteler\Theme\Cms\KettelerElementResolver;
use Ketteler\Theme\Weiterleitung\AlteAdressen;
use PHPUnit\Framework\TestCase;

class WerkseitenTest extends TestCase
{
    public function testHeftAusArtikelnummerUndName(): void
    {
        self::assertSame([2026, 5], KettelerElementResolver::heft('Z5-2026'));
        self::assertSame([2026, 5], KettelerElementResolver::heft('TEST-P-ZMV-2026-5'));
        self::assertSame([2025, 12], KettelerElementResolver::heft('Z12-2025'));
        self::assertSame([2024, 3], KettelerElementResolver::heft('XYZ', 'ZMV-Einzelheft 3/2024'));
        self::assertSame([null, null], KettelerElementResolver::heft('030-P', 'ZMV Abonnement Print'));
        self::assertSame([2010, null], KettelerElementResolver::heft('TEST-P-ZMV-FT-2010'));
    }

    public function testWeiterleitungen(): void
    {
        $liste = [
            ['alt' => '/?listview&link=0205000', 'ziel' => 'zmv-print'],
            ['alt' => '/digibib', 'ziel' => 'zmv'],
            ['alt' => '/listview?link=0500000', 'ziel' => 'eichstaetter'],
        ];
        self::assertSame('zmv-print', AlteAdressen::finden($liste, '/', ['listview' => '', 'link' => '0205000']));
        self::assertNull(AlteAdressen::finden($liste, '/', ['listview' => '', 'link' => '0999999']));
        self::assertNull(AlteAdressen::finden($liste, '/', []));
        self::assertSame('zmv', AlteAdressen::finden($liste, '/DigiBib/', ['digisubscribe' => '']));
        self::assertSame('eichstaetter', AlteAdressen::finden($liste, '/listview', ['link' => '0500000']));
        self::assertNull(AlteAdressen::finden($liste, '/Zeitschrift-ZMV/', []));
    }
}
