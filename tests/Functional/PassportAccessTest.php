<?php

namespace App\Tests\Functional;

use App\Service\PublicPassportProfileService;

class PassportAccessTest extends FunctionalWebTestCase
{
    public function testGuestIsRedirectedToLoginForPrivatePassport(): void
    {
        $this->client->request('GET', '/app/passport');

        self::assertResponseRedirects('/login?next=%2Fapp%2Fpassport');
    }

    public function testPublicPassportShowsPrivateTeaserWhenProfileIsPrivate(): void
    {
        $user = $this->createUser('private@example.com', 'Private Fan');
        $profile = static::getContainer()->get(PublicPassportProfileService::class)->ensureProfile($user);
        $profile->setIsProfilePublic(false);
        $this->entityManager->flush();

        $this->client->request('GET', sprintf('/p/%s', $profile->getShareSlug()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Account private');
        self::assertSelectorTextContains('body', (string) $profile->getUsername());
    }

    public function testPublicPassportShowsFanContentWhenProfileIsPublic(): void
    {
        $user = $this->createUser('public@example.com', 'Public Fan');
        $profile = static::getContainer()->get(PublicPassportProfileService::class)->ensureProfile($user);
        $profile->setIsProfilePublic(true);
        $this->entityManager->flush();

        $this->client->request('GET', sprintf('/p/%s', $profile->getShareSlug()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', (string) $profile->getUsername());
        self::assertSelectorTextContains('body', 'Public Passport');
    }
}
