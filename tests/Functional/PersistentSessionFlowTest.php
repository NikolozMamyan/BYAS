<?php

namespace App\Tests\Functional;

use App\Service\AuthCookieService;
use App\Service\PublicPassportProfileService;

class PersistentSessionFlowTest extends FunctionalWebTestCase
{
    public function testAuthenticatedUserIsRedirectedFromLandingToPassport(): void
    {
        $user = $this->createUser();
        static::getContainer()->get(PublicPassportProfileService::class)->ensureProfile($user)->setHasCompletedOnboarding(true);
        $this->entityManager->flush();
        $this->login($user);

        $this->client->request('GET', '/');

        self::assertResponseRedirects('/app/passport');
    }

    public function testAuthenticatedUserIsRedirectedFromLoginToRequestedPage(): void
    {
        $user = $this->createUser();
        static::getContainer()->get(PublicPassportProfileService::class)->ensureProfile($user)->setHasCompletedOnboarding(true);
        $this->entityManager->flush();
        $this->login($user);

        $this->client->request('GET', '/login?next=%2Fapp%2Fleaderboard');

        self::assertResponseRedirects('/app/leaderboard');
    }

    public function testAuthenticatedRequestRefreshesPersistentCookie(): void
    {
        $user = $this->createUser();
        static::getContainer()->get(PublicPassportProfileService::class)->ensureProfile($user)->setHasCompletedOnboarding(true);
        $this->entityManager->flush();
        $this->login($user);

        $this->client->request('GET', '/app/passport');

        self::assertResponseIsSuccessful();
        self::assertTrue(
            $this->client->getResponse()->headers->has('set-cookie'),
            'Expected a refreshed auth cookie on authenticated request.'
        );
        self::assertStringContainsString(
            AuthCookieService::AUTH_COOKIE_NAME . '=',
            implode("\n", $this->client->getResponse()->headers->all('set-cookie'))
        );
    }
}
