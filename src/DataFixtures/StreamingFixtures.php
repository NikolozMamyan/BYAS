<?php

namespace App\DataFixtures;

use App\Entity\StreamingAccount;
use App\Entity\StreamingPlayHistory;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class StreamingFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // ===== USER =====
        $user = new User();
        $user
            ->setEmail('nika.mamian@gmail.com')
            ->setDisplayName('TestUser')
            ->setRoles(['ROLE_USER'])
            ->setPassword($this->passwordHasher->hashPassword($user, 'admin123'))
            ->setGlobalXp(0)
            ->setGlobalLevel(1)
            ->setIsActive(true);

        $manager->persist($user);

        // ===== STREAMING ACCOUNT (Spotify) =====
        $account = new StreamingAccount();
        $account
            ->setUser($user)
            ->setProvider(StreamingAccount::PROVIDER_SPOTIFY)
            ->setProviderUserId('spotify_user_123')
            ->setDisplayName('Test Spotify User')
            ->setAccessToken('fake_access_token')
            ->setRefreshToken('fake_refresh_token')
            ->setExpiresAt((new \DateTimeImmutable())->modify('+1 hour'))
            ->setScopes(['user-read-recently-played'])
            ->setSyncStatus(StreamingAccount::STATUS_CONNECTED)
            ->touchLastSync();

        $manager->persist($account);

        // ===== PLAY HISTORY =====
        $tracks = [
            ['id' => 'track_001', 'name' => 'Pink Venom', 'artist' => 'BLACKPINK', 'album' => 'Born Pink'],
            ['id' => 'track_002', 'name' => 'How You Like That', 'artist' => 'BLACKPINK', 'album' => 'The Album'],
            ['id' => 'track_003', 'name' => 'Ditto', 'artist' => 'NewJeans', 'album' => 'OMG'],
            ['id' => 'track_004', 'name' => 'Hype Boy', 'artist' => 'NewJeans', 'album' => 'NewJeans'],
            ['id' => 'track_005', 'name' => 'Love Dive', 'artist' => 'IVE', 'album' => 'Love Dive'],
        ];

        $baseTime = new \DateTimeImmutable('2026-03-23 10:00:00');

        foreach ($tracks as $index => $track) {
            $playedAt = $baseTime->modify("+{$index} minutes");

            $history = new StreamingPlayHistory();
            $history
                ->setUser($user)
                ->setStreamingAccount($account)
                ->setProvider('spotify')
                ->setProviderItemId($track['id'])
                ->setProviderType('track')
                ->setItemName($track['name'])
                ->setArtistName($track['artist'])
                ->setAlbumName($track['album'])
                ->setDurationMs(rand(170000, 210000))
                ->setPlayedAt($playedAt)
                ->setRawData([
                    'fixture' => true,
                    'track' => $track,
                ]);

            $manager->persist($history);
        }

        $manager->flush();
    }
}