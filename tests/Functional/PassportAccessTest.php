<?php

namespace App\Tests\Functional;

use App\Entity\AppNotification;
use App\Repository\AppNotificationRepository;
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

    public function testPassportPromptsStreamingConnectionWhenNoProviderIsConnected(): void
    {
        $user = $this->createUser('streaming-empty@example.com', 'Streaming Empty');
        static::getContainer()->get(PublicPassportProfileService::class)->completeOnboarding($user);
        $this->login($user);

        $this->client->request('GET', '/app/passport');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#streamingOnboardingTitle', 'Activate your passport with your plays');
        self::assertSelectorExists('a[href="/app/connect/spotify"]');
        self::assertSelectorExists('a[href="/app/connect/youtube"]');

        $repository = static::getContainer()->get(AppNotificationRepository::class);
        $notification = $repository->findLatestOfTypeForUser($user, AppNotification::TYPE_STREAMING_SETUP_REMINDER);

        self::assertNotNull($notification);
        self::assertFalse($notification->isRead());
    }

    public function testPassportDoesNotDuplicateUnreadStreamingConnectionReminder(): void
    {
        $user = $this->createUser('streaming-reminder@example.com', 'Streaming Reminder');
        static::getContainer()->get(PublicPassportProfileService::class)->completeOnboarding($user);
        $this->login($user);

        $this->client->request('GET', '/app/passport');
        $this->client->request('GET', '/app/passport');

        $repository = static::getContainer()->get(AppNotificationRepository::class);
        $notifications = $repository->findBy([
            'user' => $user,
            'type' => AppNotification::TYPE_STREAMING_SETUP_REMINDER,
        ]);

        self::assertCount(1, $notifications);
    }
}
