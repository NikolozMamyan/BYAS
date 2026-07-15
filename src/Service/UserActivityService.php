<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserActivityDay;
use App\Repository\UserActivityDayRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class UserActivityService
{
    public function __construct(
        private readonly UserActivityDayRepository $activityRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function recordAppOpen(User $user): UserActivityDay
    {
        $today = new \DateTimeImmutable('today');
        $activity = $this->activityRepository->findOneBy([
            'user' => $user,
            'activityDate' => $today,
        ]);

        if (!$activity instanceof UserActivityDay) {
            $activity = (new UserActivityDay())
                ->setUser($user)
                ->setActivityDate($today)
                ->setGlobalRank($this->userRepository->getGlobalRankPosition($user));

            $this->entityManager->persist($activity);
            $this->entityManager->flush();
        }

        return $activity;
    }

    public function currentStreak(User $user): int
    {
        $activities = $this->activityRepository->findRecentForUser($user);

        if ($activities === []) {
            return 0;
        }

        $expected = new \DateTimeImmutable('today');
        $streak = 0;

        foreach ($activities as $activity) {
            if ($activity->getActivityDate()->format('Y-m-d') !== $expected->format('Y-m-d')) {
                break;
            }

            $streak++;
            $expected = $expected->modify('-1 day');
        }

        return $streak;
    }

    public function weeklyRankGain(User $user, int $currentRank, \DateTimeImmutable $weekStart): ?int
    {
        $firstSnapshot = $this->activityRepository->findOldestSince($user, $weekStart);
        $previousRank = $firstSnapshot?->getGlobalRank();

        if ($previousRank === null) {
            return null;
        }

        return max(0, $previousRank - $currentRank);
    }
}
