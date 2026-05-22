<?php

namespace App\Service;

class LevelBadgeCatalog
{
    /**
     * @var array<int, string>
     */
    private const EARLY_LEVEL_TITLES = [
        1 => 'Spark',
        2 => 'Pulse',
        3 => 'Glow',
        4 => 'Aura',
        5 => 'Orbit',
        6 => 'Drift',
        7 => 'Nova',
        8 => 'Comet',
        9 => 'Eclipse',
    ];

    /**
     * @var array<int, string>
     */
    private const TIER_MAIN_TITLES = [
        10 => 'Ascend',
        20 => 'Astra',
        30 => 'Aether',
        40 => 'Galaxy',
        50 => 'Nebula',
        60 => 'Umbra',
        70 => 'Supernova',
        80 => 'Eon',
        90 => 'Hypernova',
    ];

    /**
     * @var array<int, string>
     */
    private const TIER_SUB_TITLES = [
        0 => 'Core',
        1 => 'Flux',
        2 => 'Echo',
        3 => 'Phase',
        4 => 'Vector',
        5 => 'Shift',
        6 => 'Prime',
        7 => 'Elite',
        8 => 'Apex',
        9 => 'Ethereal',
    ];

    /**
     * @return array{level:int,mainTitle:string,subLevelTitle:?string,fullTitle:string}
     */
    public function forLevel(int $level): array
    {
        $normalizedLevel = max(1, $level);

        if (isset(self::EARLY_LEVEL_TITLES[$normalizedLevel])) {
            $mainTitle = self::EARLY_LEVEL_TITLES[$normalizedLevel];

            return $this->formatLevel($normalizedLevel, $mainTitle, null);
        }

        if ($normalizedLevel >= 100) {
            if ($normalizedLevel >= 150) {
                return $this->formatLevel($normalizedLevel, 'Celestial', null);
            }

            return $this->formatLevel($normalizedLevel, 'Cosmic', null);
        }

        $tierStart = ((int) floor(($normalizedLevel - 10) / 10) * 10) + 10;
        $mainTitle = self::TIER_MAIN_TITLES[$tierStart] ?? 'Cosmic';
        $subLevelTitle = self::TIER_SUB_TITLES[$normalizedLevel - $tierStart] ?? null;

        return $this->formatLevel($normalizedLevel, $mainTitle, $subLevelTitle);
    }

    /**
     * @return array{level:int,mainTitle:string,subLevelTitle:?string,fullTitle:string}
     */
    private function formatLevel(int $level, string $mainTitle, ?string $subLevelTitle): array
    {
        return [
            'level' => $level,
            'mainTitle' => $mainTitle,
            'subLevelTitle' => $subLevelTitle,
            'fullTitle' => $subLevelTitle !== null ? sprintf('%s %s', $mainTitle, $subLevelTitle) : $mainTitle,
        ];
    }
}
