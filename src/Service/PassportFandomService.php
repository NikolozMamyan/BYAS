<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserFandom;
use App\Repository\UserFandomRepository;

final class PassportFandomService
{
    public function __construct(
        private readonly UserFandomRepository $userFandomRepository,
    ) {
    }

    /**
     * @return UserFandom[]
     */
    public function visibleFor(User $user): array
    {
        $nonYoutubeArtistIds = [];
        $youtubeArtistIds = [];

        foreach ($user->getXpTransactions() as $transaction) {
            $artistId = $transaction->getArtist()?->getId();
            if ($artistId === null) {
                continue;
            }

            $provider = strtolower((string) ($transaction->getMetadata()['provider'] ?? ''));
            if ($provider === 'youtube') {
                $youtubeArtistIds[$artistId] = true;
            } else {
                $nonYoutubeArtistIds[$artistId] = true;
            }
        }

        $fandoms = array_values(array_filter(
            $user->getUserFandoms()->toArray(),
            static function (UserFandom $fandom) use ($nonYoutubeArtistIds, $youtubeArtistIds): bool {
                $artistId = $fandom->getArtist()?->getId();

                if ($artistId === null || $fandom->getXp() === 0) {
                    return true;
                }

                return isset($nonYoutubeArtistIds[$artistId]) || !isset($youtubeArtistIds[$artistId]);
            },
        ));

        usort($fandoms, static fn (UserFandom $left, UserFandom $right): int => $right->getXp() <=> $left->getXp());

        return $fandoms;
    }

    /**
     * @param UserFandom[] $fandoms
     */
    public function firstFandom(array $fandoms): ?UserFandom
    {
        if ($fandoms === []) {
            return null;
        }

        usort($fandoms, static function (UserFandom $left, UserFandom $right): int {
            $leftAt = $left->getFirstEngagedAt()?->getTimestamp() ?? PHP_INT_MAX;
            $rightAt = $right->getFirstEngagedAt()?->getTimestamp() ?? PHP_INT_MAX;

            return ($leftAt <=> $rightAt) ?: (($left->getId() ?? PHP_INT_MAX) <=> ($right->getId() ?? PHP_INT_MAX));
        });

        return $fandoms[0];
    }

    /**
     * @param UserFandom[] $fandoms
     *
     * @return array<int, int|null>
     */
    public function ranks(array $fandoms): array
    {
        $ranks = [];

        foreach ($fandoms as $fandom) {
            if ($fandom->getId() !== null) {
                $ranks[$fandom->getId()] = $this->userFandomRepository->getRankPosition($fandom);
            }
        }

        return $ranks;
    }
}
