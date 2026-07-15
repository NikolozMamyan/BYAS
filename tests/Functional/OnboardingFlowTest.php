<?php

namespace App\Tests\Functional;

use App\Entity\Artist;
use App\Entity\UserFandom;
use App\Service\PublicPassportProfileService;

final class OnboardingFlowTest extends FunctionalWebTestCase
{
    public function testReadyScreenPersistsTheSelectedFandom(): void
    {
        $user = $this->createUser('onboarding@example.com', 'Onboarding Fan');
        static::getContainer()->get(PublicPassportProfileService::class)->ensureProfile($user);
        $this->login($user);

        $this->client->request('GET', '/app/onboarding/ready?fandom=bts');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ready-fandom-name', 'ARMY');

        $artist = $this->entityManager->getRepository(Artist::class)->findOneBy(['slug' => 'bts']);
        self::assertInstanceOf(Artist::class, $artist);

        $fandom = $this->entityManager->getRepository(UserFandom::class)->findOneBy([
            'user' => $user,
            'artist' => $artist,
        ]);
        self::assertInstanceOf(UserFandom::class, $fandom);
        self::assertSame('img/choseGroup/BTS.png', $artist->getCoverImageUrl());
        self::assertTrue($user->getProfile()?->hasCompletedOnboarding());
    }
}
