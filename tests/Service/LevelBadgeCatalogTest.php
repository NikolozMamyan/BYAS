<?php

namespace App\Tests\Service;

use App\Service\LevelBadgeCatalog;
use PHPUnit\Framework\TestCase;

class LevelBadgeCatalogTest extends TestCase
{
    public function testEarlyLevelsUseStandaloneTitles(): void
    {
        $catalog = new LevelBadgeCatalog();

        self::assertSame([
            'level' => 1,
            'mainTitle' => 'Spark',
            'subLevelTitle' => null,
            'fullTitle' => 'Spark',
        ], $catalog->forLevel(1));

        self::assertSame('Eclipse', $catalog->forLevel(9)['fullTitle']);
    }

    public function testTierLevelsUseMainAndSubTitles(): void
    {
        $catalog = new LevelBadgeCatalog();

        self::assertSame('Ascend', $catalog->forLevel(10)['mainTitle']);
        self::assertSame('Core', $catalog->forLevel(10)['subLevelTitle']);
        self::assertSame('Ascend Ethereal', $catalog->forLevel(19)['fullTitle']);
        self::assertSame('Galaxy Shift', $catalog->forLevel(45)['fullTitle']);
        self::assertSame('Hypernova Ethereal', $catalog->forLevel(99)['fullTitle']);
    }

    public function testLateLevelsUseCosmicAndCelestialFallbacks(): void
    {
        $catalog = new LevelBadgeCatalog();

        self::assertSame('Cosmic', $catalog->forLevel(100)['fullTitle']);
        self::assertSame('Cosmic', $catalog->forLevel(149)['fullTitle']);
        self::assertSame('Celestial', $catalog->forLevel(150)['fullTitle']);
        self::assertSame('Celestial', $catalog->forLevel(220)['fullTitle']);
    }
}
