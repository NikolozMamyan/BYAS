<?php

namespace App\Tests\Service;

use App\Service\BadgeCatalog;
use App\Service\XpEngine;
use PHPUnit\Framework\TestCase;

class BadgeCatalogTest extends TestCase
{
    public function testCatalogContainsCorePhaseOneBadges(): void
    {
        $catalog = new BadgeCatalog();
        $badges = $catalog->all();

        self::assertArrayHasKey(BadgeCatalog::FIRST_STREAM, $badges);
        self::assertSame(XpEngine::SOURCE_STREAMING_PLAY, $badges[BadgeCatalog::FIRST_STREAM]['ruleConfig']['sourceType']);
        self::assertSame(10, $badges[BadgeCatalog::GLOBAL_LEVEL_10]['ruleConfig']['level']);
        self::assertSame('fandom', $badges[BadgeCatalog::FANDOM_LEVEL_10]['scope']);
    }
}
