<?php

namespace App\Service;

class BadgeCatalog
{
    public const FIRST_STREAM = 'first_stream';
    public const GLOBAL_LEVEL_10 = 'global_level_10';
    public const FANDOM_LEVEL_10 = 'fandom_level_10';

    /**
     * @return array<string, array{name:string,description:string,scope:string,ruleType:string,ruleConfig:array<string, mixed>}>
     */
    public function all(): array
    {
        return [
            self::FIRST_STREAM => [
                'name' => 'First Stream',
                'description' => 'First streaming play counted on BYAS.',
                'scope' => 'global',
                'ruleType' => 'source_event',
                'ruleConfig' => ['sourceType' => XpEngine::SOURCE_STREAMING_PLAY],
            ],
            self::GLOBAL_LEVEL_10 => [
                'name' => 'Global Level 10',
                'description' => 'Reached global fan level 10.',
                'scope' => 'global',
                'ruleType' => 'global_level',
                'ruleConfig' => ['level' => 10],
            ],
            self::FANDOM_LEVEL_10 => [
                'name' => 'Fandom Level 10',
                'description' => 'Reached fandom level 10 with an artist.',
                'scope' => 'fandom',
                'ruleType' => 'fandom_level',
                'ruleConfig' => ['level' => 10],
            ],
        ];
    }

    /**
     * @return array{name:string,description:string,scope:string,ruleType:string,ruleConfig:array<string, mixed>}|null
     */
    public function get(string $code): ?array
    {
        $badges = $this->all();

        return $badges[$code] ?? null;
    }
}
