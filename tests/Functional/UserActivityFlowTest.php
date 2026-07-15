<?php

namespace App\Tests\Functional;

use App\Entity\UserActivityDay;
use App\Repository\UserActivityDayRepository;
use App\Service\PublicPassportProfileService;
use App\Service\UserActivityService;

final class UserActivityFlowTest extends FunctionalWebTestCase
{
    public function testAuthenticatedAppOpenIsRecordedOncePerDayAndBuildsAStreak(): void
    {
        $user = $this->createUser('streak@example.com', 'Streak Fan');
        static::getContainer()->get(PublicPassportProfileService::class)->completeOnboarding($user);
        $this->login($user);

        $this->client->request('GET', '/app/passport');
        $this->client->request('GET', '/app/passport');

        $repository = static::getContainer()->get(UserActivityDayRepository::class);
        self::assertCount(1, $repository->findBy(['user' => $user]));

        $managedUser = $this->entityManager->find(\App\Entity\User::class, $user->getId());
        self::assertNotNull($managedUser);

        $yesterday = (new UserActivityDay())
            ->setUser($managedUser)
            ->setActivityDate(new \DateTimeImmutable('yesterday'))
            ->setGlobalRank(1);
        $this->entityManager->persist($yesterday);
        $this->entityManager->flush();

        self::assertSame(2, static::getContainer()->get(UserActivityService::class)->currentStreak($managedUser));
    }
}
