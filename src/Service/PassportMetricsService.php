<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\StreamingPlayHistoryRepository;
use App\Repository\UserFandomRepository;
use App\Repository\UserRepository;
use App\Repository\XpTransactionRepository;

final class PassportMetricsService
{
    public function __construct(
        private readonly XpTransactionRepository $xpTransactionRepository,
        private readonly StreamingPlayHistoryRepository $playHistoryRepository,
        private readonly UserRepository $userRepository,
        private readonly UserFandomRepository $userFandomRepository,
        private readonly UserActivityService $activityService,
        private readonly PassportFandomService $fandomService,
    ) {
    }

    /**
     * @return array{weeklyXp:int,weeklyListeningMinutes:int,weeklyRankGain:?int,currentStreak:int,countryRank:?int,firstFandomRank:?int,firstFandomName:?string,missionCount:?int}
     */
    public function forUser(User $user, int $globalRank): array
    {
        $weekStart = new \DateTimeImmutable('monday this week 00:00:00');
        $fandoms = $this->fandomService->visibleFor($user);
        $firstFandom = $this->fandomService->firstFandom($fandoms);

        return [
            'weeklyXp' => $this->xpTransactionRepository->sumAwardedSince($user, $weekStart),
            'weeklyListeningMinutes' => (int) round($this->playHistoryRepository->sumDurationMsSince($user, $weekStart) / 60000),
            'weeklyRankGain' => $this->activityService->weeklyRankGain($user, $globalRank, $weekStart),
            'currentStreak' => $this->activityService->currentStreak($user),
            'countryRank' => $this->userRepository->getCountryRankPosition($user),
            'firstFandomRank' => $firstFandom instanceof \App\Entity\UserFandom
                ? $this->userFandomRepository->getRankPosition($firstFandom)
                : null,
            'firstFandomName' => $firstFandom?->getArtist()?->getName(),
            'missionCount' => null,
        ];
    }
}
