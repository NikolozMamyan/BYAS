<?php

namespace App\Tests\Functional;

use App\Entity\AppNotification;
use App\Repository\AppNotificationRepository;

class NotificationControllerTest extends FunctionalWebTestCase
{
    public function testNotificationInboxDisplaysStoredNotifications(): void
    {
        $user = $this->createUser('notify@example.com', 'Notify Fan');
        $this->persistNotification($user, 'Sync completed', 'Spotify sync finished with 12 XP.');
        $this->login($user);

        $this->client->request('GET', '/app/notifications');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Sync completed');
        self::assertSelectorTextContains('body', 'Spotify sync finished with 12 XP.');
    }

    public function testNotificationCanBeDeletedFromInbox(): void
    {
        $user = $this->createUser('delete@example.com', 'Delete Fan');
        $notification = $this->persistNotification($user, 'Delete me', 'This notification should be removed.');
        $this->login($user);

        $crawler = $this->client->request('GET', '/app/notifications');
        $form = $crawler->filter(sprintf('form[action="/app/notifications/%d/delete"]', $notification->getId()))->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/app/notifications?deleted=1&unread=0');

        $repository = static::getContainer()->get(AppNotificationRepository::class);
        self::assertNull($repository->find($notification->getId()));
    }

    public function testOpeningNotificationMarksItAsRead(): void
    {
        $user = $this->createUser('read@example.com', 'Read Fan');
        $notification = $this->persistNotification($user, 'Open me', 'This notification becomes read.', '/app/passport');
        $this->login($user);

        $this->client->request('GET', sprintf('/app/notifications/%d/open', $notification->getId()));

        self::assertResponseRedirects('/app/passport');

        $repository = static::getContainer()->get(AppNotificationRepository::class);
        $storedNotification = $repository->find($notification->getId());

        self::assertNotNull($storedNotification);
        self::assertTrue($storedNotification->isRead());
    }

    private function persistNotification(
        \App\Entity\User $user,
        string $title,
        string $body,
        ?string $targetUrl = '/app/notifications',
    ): AppNotification {
        $notification = (new AppNotification())
            ->setUser($user)
            ->setType(AppNotification::TYPE_SYNC_COMPLETED)
            ->setTitle($title)
            ->setBody($body)
            ->setTargetUrl($targetUrl);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }
}
