<?php

namespace App\Tests\Service;

use App\Entity\Artist;
use App\Entity\StreamingPlayHistory;
use App\Entity\User;
use App\Entity\UserFandom;
use App\Repository\XpTransactionRepository;
use App\Service\BadgeAwarder;
use App\Service\XpEngine;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class XpEngineTest extends TestCase
{
    public function testAwardStreamingPlayAddsGlobalAndFandomXp(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->expects(self::atLeastOnce())->method('persist');

        $xpRepository = $this->createMock(XpTransactionRepository::class);
        $xpRepository->method('existsForSource')->willReturn(false);

        $badgeAwarder = $this->createMock(BadgeAwarder::class);
        $badgeAwarder->expects(self::once())
            ->method('awardForXpEvent')
            ->with(
                self::isInstanceOf(User::class),
                XpEngine::SOURCE_STREAMING_PLAY,
                self::isString(),
                self::isInstanceOf(UserFandom::class),
                self::isArray()
            );

        $engine = new XpEngine($entityManager, $xpRepository, $badgeAwarder);
        $user = (new User())->setEmail('fan@example.com')->setDisplayName('Fan');
        $history = $this->buildHistory($user);

        $transaction = $engine->awardStreamingPlay($history);

        self::assertNotNull($transaction);
        self::assertSame(10, $transaction->getXpAmount());
        self::assertSame(10, $user->getGlobalXp());
        self::assertSame(1, $user->getGlobalLevel());
        self::assertInstanceOf(Artist::class, $transaction->getArtist());
        self::assertInstanceOf(UserFandom::class, $transaction->getUserFandom());
        self::assertSame(10, $transaction->getUserFandom()->getXp());
    }

    public function testAwardXpSkipsExistingSourceReference(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $xpRepository = $this->createMock(XpTransactionRepository::class);
        $xpRepository->method('existsForSource')->willReturn(true);

        $badgeAwarder = $this->createMock(BadgeAwarder::class);
        $badgeAwarder->expects(self::never())->method('awardForXpEvent');

        $engine = new XpEngine($entityManager, $xpRepository, $badgeAwarder);
        $user = (new User())->setEmail('fan@example.com')->setDisplayName('Fan');

        $transaction = $engine->awardXp(
            user: $user,
            xpAmount: 10,
            sourceType: XpEngine::SOURCE_STREAMING_PLAY,
            sourceReference: 'spotify:track:123',
            reason: 'Duplicate stream',
            occurredAt: new \DateTimeImmutable(),
        );

        self::assertNull($transaction);
        self::assertSame(0, $user->getGlobalXp());
    }

    public function testProgressForXpUsesLevelCurve(): void
    {
        $engine = new XpEngine(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(XpTransactionRepository::class),
            $this->createMock(BadgeAwarder::class),
        );

        self::assertSame(1, $engine->levelForXp(0));
        self::assertSame(2, $engine->levelForXp(100));
        self::assertSame(['current' => 100, 'next' => 400, 'percent' => 50.0], $engine->progressForXp(250));
    }

    private function buildHistory(User $user): StreamingPlayHistory
    {
        return (new StreamingPlayHistory())
            ->setUser($user)
            ->setProvider('spotify')
            ->setProviderItemId('track-1')
            ->setProviderType('track')
            ->setItemName('Test Song')
            ->setArtistName('Test Artist')
            ->setAlbumName('Test Album')
            ->setDurationMs(180000)
            ->setPlayedAt(new \DateTimeImmutable('2026-04-10 12:00:00'))
            ->setRawData([]);
    }
}
