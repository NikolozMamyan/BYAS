<?php

namespace App\Tests\Service;

use App\Entity\StreamingAccount;
use App\Entity\StreamingPlayHistory;
use App\Entity\User;
use App\Repository\StreamingAccountRepository;
use App\Repository\StreamingPlayHistoryRepository;
use App\Repository\UserRepository;
use App\Service\AppleMusicService;
use App\Service\NotificationCenter;
use App\Service\SpotifyDataService;
use App\Service\StreamingSyncService;
use App\Service\XpEngine;
use App\Service\YouTubeDataService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class StreamingSyncServiceTest extends TestCase
{
    public function testSpotifySyncStoresTheArtistPhotoInsteadOfTheAlbumArtwork(): void
    {
        $user = (new User())->setEmail('fan@example.com')->setDisplayName('Fan');
        $account = (new StreamingAccount())
            ->setUser($user)
            ->setProvider(StreamingAccount::PROVIDER_SPOTIFY)
            ->setProviderUserId('spotify-user');

        $accountRepository = $this->createMock(StreamingAccountRepository::class);
        $accountRepository->method('findConnectedByUser')->with($user)->willReturn([$account]);

        $historyRepository = $this->createMock(StreamingPlayHistoryRepository::class);
        $historyRepository->method('exists')->willReturn(false);

        $spotify = $this->createMock(SpotifyDataService::class);
        $spotify->method('getRecentlyPlayed')->willReturn([
            'items' => [[
                'played_at' => '2026-07-15T10:00:00+00:00',
                'track' => [
                    'id' => 'track-1',
                    'name' => 'A song',
                    'artists' => [['name' => 'SAVV']],
                    'album' => ['name' => 'An album'],
                    'duration_ms' => 180000,
                ],
            ]],
        ]);
        $spotify->method('getTopArtists')->willReturn([
            'items' => [[
                'name' => 'SAVV',
                'images' => [['url' => 'https://images.example/savv-artist.jpg']],
            ]],
        ]);

        $capturedHistory = null;
        $xpEngine = $this->createMock(XpEngine::class);
        $xpEngine->expects(self::once())
            ->method('awardStreamingPlay')
            ->willReturnCallback(static function (StreamingPlayHistory $history) use (&$capturedHistory): null {
                $capturedHistory = $history;

                return null;
            });

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('getGlobalRankPosition')->willReturn(1);

        $service = new StreamingSyncService(
            $accountRepository,
            $historyRepository,
            $spotify,
            $this->createMock(YouTubeDataService::class),
            $this->createMock(AppleMusicService::class),
            $xpEngine,
            $this->createMock(EntityManagerInterface::class),
            $userRepository,
            $this->createMock(NotificationCenter::class),
        );

        $result = $service->syncUser($user);

        self::assertSame(1, $result['totalInserted']);
        self::assertInstanceOf(StreamingPlayHistory::class, $capturedHistory);
        self::assertSame(
            'https://images.example/savv-artist.jpg',
            $capturedHistory->getRawData()['byas_artist_image_url'] ?? null,
        );
    }
}
