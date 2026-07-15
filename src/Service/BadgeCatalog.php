<?php

namespace App\Service;

class BadgeCatalog
{
    public const FIRST_STREAM = 'first_stream';
    public const GLOBAL_LEVEL_10 = 'global_level_10';
    public const FANDOM_LEVEL_10 = 'fandom_level_10';
    public const LIMITED_FOUNDER = 'limited_founder';
    public const EVENT_CONCERT = 'event_concert';
    public const STREAK_30_DAYS = 'streak_30_days';
    public const MILESTONE_LEVEL_50 = 'milestone_level_50';
    public const SOCIAL_TOP_1_PERCENT = 'social_top_1_percent';
    public const COLLECTION_ALBUM = 'collection_album';

    public const SHOWCASE_CODES = [
        self::LIMITED_FOUNDER,
        self::EVENT_CONCERT,
        self::STREAK_30_DAYS,
        self::MILESTONE_LEVEL_50,
        self::SOCIAL_TOP_1_PERCENT,
        self::COLLECTION_ALBUM,
    ];

    /**
     * @return array<string, array{name:string,description:string,iconUrl:?string,scope:string,ruleType:string,ruleConfig:array<string, mixed>}>
     */
    public function all(): array
    {
        return [
            self::FIRST_STREAM => [
                'name' => 'First Stream',
                'description' => 'First streaming play counted on BYAS.',
                'iconUrl' => null,
                'scope' => 'global',
                'ruleType' => 'source_event',
                'ruleConfig' => ['sourceType' => XpEngine::SOURCE_STREAMING_PLAY],
            ],
            self::GLOBAL_LEVEL_10 => [
                'name' => 'Global Level 10',
                'description' => 'Reached global fan level 10.',
                'iconUrl' => null,
                'scope' => 'global',
                'ruleType' => 'global_level',
                'ruleConfig' => ['level' => 10],
            ],
            self::FANDOM_LEVEL_10 => [
                'name' => 'Fandom Level 10',
                'description' => 'Reached fandom level 10 with an artist.',
                'iconUrl' => null,
                'scope' => 'fandom',
                'ruleType' => 'fandom_level',
                'ruleConfig' => ['level' => 10],
            ],
            self::LIMITED_FOUNDER => [
                'name' => 'Founder',
                'description' => 'Limited badge reserved for the founding BYAS community.',
                'iconUrl' => 'img/badges/limited_founder.png',
                'scope' => 'global',
                'ruleType' => 'manual',
                'ruleConfig' => ['collection' => 'limited'],
            ],
            self::EVENT_CONCERT => [
                'name' => 'Concert',
                'description' => 'Event badge celebrating a concert experience.',
                'iconUrl' => 'img/badges/event_concert.png',
                'scope' => 'global',
                'ruleType' => 'manual',
                'ruleConfig' => ['collection' => 'event'],
            ],
            self::STREAK_30_DAYS => [
                'name' => '30 Day Streak',
                'description' => 'Used BYAS for 30 consecutive days.',
                'iconUrl' => 'img/badges/30_days.png',
                'scope' => 'global',
                'ruleType' => 'streak',
                'ruleConfig' => ['days' => 30],
            ],
            self::MILESTONE_LEVEL_50 => [
                'name' => 'Level 50',
                'description' => 'Reached global fan level 50.',
                'iconUrl' => 'img/badges/lvl_50.png',
                'scope' => 'global',
                'ruleType' => 'global_level',
                'ruleConfig' => ['level' => 50],
            ],
            self::SOCIAL_TOP_1_PERCENT => [
                'name' => 'Top 1%',
                'description' => 'Ranked among the top one percent of the BYAS community.',
                'iconUrl' => 'img/badges/social_record_1.png',
                'scope' => 'global',
                'ruleType' => 'rank_percentile',
                'ruleConfig' => ['percentile' => 1],
            ],
            self::COLLECTION_ALBUM => [
                'name' => 'Album Collector',
                'description' => 'Added an album to the BYAS collection.',
                'iconUrl' => 'img/badges/colector_badge.png',
                'scope' => 'global',
                'ruleType' => 'collection',
                'ruleConfig' => ['type' => 'album'],
            ],
        ];
    }

    /**
     * @return array{name:string,description:string,iconUrl:?string,scope:string,ruleType:string,ruleConfig:array<string, mixed>}|null
     */
    public function get(string $code): ?array
    {
        $badges = $this->all();

        return $badges[$code] ?? null;
    }
}
