<?php

namespace App\Service;

use App\Entity\StreamingAccount;
use App\Entity\StreamingPlayHistory;
use App\Entity\User;
use App\Repository\StreamingAccountRepository;
use App\Repository\StreamingPlayHistoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class StreamingSyncService
{
    public function __construct(
        private readonly StreamingAccountRepository $accountRepository,
        private readonly StreamingPlayHistoryRepository $historyRepository,
        private readonly SpotifyDataService $spotifyDataService,
        private readonly YouTubeDataService $youTubeDataService,
        private readonly AppleMusicService $appleMusicService,
        private readonly XpEngine $xpEngine,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly NotificationCenter $notificationCenter,
    ) {
    }

    public function syncUser(User $user): array
    {
        $previousRank = $this->userRepository->getGlobalRankPosition($user);
        $accounts = $this->accountRepository->findConnectedByUser($user);

        $providers = [];
        $totalFetched = 0;
        $totalInserted = 0;
        $totalSkipped = 0;
        $totalXpAwarded = 0;

        foreach ($accounts as $account) {
            try {
                $result = $this->syncAccount($user, $account);
            } catch (\Throwable $exception) {
                $result = [
                    'provider' => $account->getProvider(),
                    'status' => 'error',
                    'fetched' => 0,
                    'inserted' => 0,
                    'skipped' => 0,
                    'xpAwarded' => 0,
                    'message' => $exception->getMessage(),
                ];
            }

            $providers[] = $result;
            $totalFetched += $result['fetched'];
            $totalInserted += $result['inserted'];
            $totalSkipped += $result['skipped'];
            $totalXpAwarded += $result['xpAwarded'] ?? 0;
        }

        if ($totalInserted > 0) {
            $this->entityManager->flush();
        }

        $currentRank = $this->userRepository->getGlobalRankPosition($user);
        $this->notificationCenter->notifySyncCompleted($user, $providers, $totalInserted, $totalXpAwarded);

        if ($currentRank < $previousRank) {
            $this->notificationCenter->notifyRankImproved($user, $previousRank, $currentRank);
        }

        $this->entityManager->flush();

        return [
            'accounts' => count($accounts),
            'totalFetched' => $totalFetched,
            'totalInserted' => $totalInserted,
            'totalSkipped' => $totalSkipped,
            'totalXpAwarded' => $totalXpAwarded,
            'previousRank' => $previousRank,
            'currentRank' => $currentRank,
            'providers' => $providers,
        ];
    }

    private function syncAccount(User $user, StreamingAccount $account): array
    {
        return match ($account->getProvider()) {
            StreamingAccount::PROVIDER_SPOTIFY => $this->syncSpotifyAccount($user, $account),
            StreamingAccount::PROVIDER_APPLE_MUSIC => $this->syncAppleMusicAccount($user, $account),
            StreamingAccount::PROVIDER_YOUTUBE => $this->syncYoutubeAccount($user, $account),
            default => [
                'provider' => $account->getProvider(),
                'status' => 'unsupported',
                'fetched' => 0,
                'inserted' => 0,
                'skipped' => 0,
                'xpAwarded' => 0,
                'message' => sprintf('Unsupported provider "%s".', $account->getProvider()),
            ],
        };
    }

    private function syncSpotifyAccount(User $user, StreamingAccount $account): array
    {
        $data = $this->spotifyDataService->getRecentlyPlayed($user, 50);
        $items = $data['items'] ?? [];

        $fetched = 0;
        $inserted = 0;
        $skipped = 0;
        $xpAwarded = 0;
        $seenPlays = [];

        foreach ($items as $item) {
            $fetched++;

            if (!is_array($item)) {
                $skipped++;
                continue;
            }

            if (!isset($item['track']) || !is_array($item['track'])) {
                $skipped++;
                continue;
            }

            $track = $item['track'];

            $providerItemId = (string) ($track['id'] ?? '');
            $playedAtRaw = (string) ($item['played_at'] ?? '');

            if ($providerItemId === '' || $playedAtRaw === '') {
                $skipped++;
                continue;
            }

            try {
                $playedAt = new \DateTimeImmutable($playedAtRaw);
            } catch (\Throwable) {
                $skipped++;
                continue;
            }

            if ($this->historyRepository->exists($account, $providerItemId, $playedAt)) {
                $skipped++;
                continue;
            }

            $playKey = sprintf('%s:%s:%s', $account->getId() ?? spl_object_hash($account), $providerItemId, $playedAt->format('U.u'));

            if (isset($seenPlays[$playKey])) {
                $skipped++;
                continue;
            }

            $seenPlays[$playKey] = true;

            $history = new StreamingPlayHistory();
            $history
                ->setUser($user)
                ->setStreamingAccount($account)
                ->setProvider($account->getProvider())
                ->setProviderItemId($providerItemId)
                ->setProviderType('track')
                ->setItemName((string) ($track['name'] ?? 'Unknown title'))
                ->setArtistName($this->extractSpotifyArtistName($track))
                ->setAlbumName($this->extractSpotifyAlbumName($track))
                ->setDurationMs(isset($track['duration_ms']) ? (int) $track['duration_ms'] : null)
                ->setPlayedAt($playedAt)
                ->setRawData($item);

            $this->entityManager->persist($history);
            $transaction = $this->xpEngine->awardStreamingPlay($history);
            $xpAwarded += $transaction?->getXpAmount() ?? 0;
            $inserted++;
        }

        if ($fetched > 0) {
            $account->touchLastSync();
            $this->entityManager->persist($account);
        }

        return [
            'provider' => $account->getProvider(),
            'status' => 'success',
            'fetched' => $fetched,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'xpAwarded' => $xpAwarded,
            'message' => 'Spotify sync completed.',
        ];
    }

    private function syncYoutubeAccount(User $user, StreamingAccount $account): array
    {
        $data = $this->youTubeDataService->getLikedVideos($user, 50);
        $items = $data['items'] ?? [];

        $fetched = 0;
        $inserted = 0;
        $skipped = 0;
        $xpAwarded = 0;
        $seenPlays = [];

        foreach ($items as $item) {
            $fetched++;

            if (!is_array($item)) {
                $skipped++;
                continue;
            }

            $snippet = $item['snippet'] ?? null;
            $contentDetails = $item['contentDetails'] ?? null;

            if (!is_array($snippet) || !is_array($contentDetails)) {
                $skipped++;
                continue;
            }

            $providerItemId = (string) ($contentDetails['videoId'] ?? '');
            $playedAtRaw = (string) ($snippet['publishedAt'] ?? '');

            if ($providerItemId === '' || $playedAtRaw === '') {
                $skipped++;
                continue;
            }

            try {
                $playedAt = new \DateTimeImmutable($playedAtRaw);
            } catch (\Throwable) {
                $skipped++;
                continue;
            }

            if ($this->historyRepository->exists($account, $providerItemId, $playedAt)) {
                $skipped++;
                continue;
            }

            $playKey = sprintf('%s:%s:%s', $account->getId() ?? spl_object_hash($account), $providerItemId, $playedAt->format('U.u'));

            if (isset($seenPlays[$playKey])) {
                $skipped++;
                continue;
            }

            $seenPlays[$playKey] = true;

            $history = new StreamingPlayHistory();
            $history
                ->setUser($user)
                ->setStreamingAccount($account)
                ->setProvider($account->getProvider())
                ->setProviderItemId($providerItemId)
                ->setProviderType('video')
                ->setItemName((string) ($snippet['title'] ?? 'Unknown video'))
                ->setArtistName($this->extractYoutubeChannelName($snippet))
                ->setAlbumName(null)
                ->setDurationMs(null)
                ->setPlayedAt($playedAt)
                ->setRawData($item);

            $this->entityManager->persist($history);
            $transaction = $this->xpEngine->awardStreamingPlay($history);
            $xpAwarded += $transaction?->getXpAmount() ?? 0;
            $inserted++;
        }

        if ($fetched > 0) {
            $account->touchLastSync();
            $this->entityManager->persist($account);
        }

        return [
            'provider' => $account->getProvider(),
            'status' => 'success',
            'fetched' => $fetched,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'xpAwarded' => $xpAwarded,
            'message' => 'YouTube sync completed from liked videos.',
        ];
    }

    private function syncAppleMusicAccount(User $user, StreamingAccount $account): array
    {
        if (!$this->appleMusicService->isConfigured()) {
            return [
                'provider' => $account->getProvider(),
                'status' => 'skipped',
                'fetched' => 0,
                'inserted' => 0,
                'skipped' => 0,
                'xpAwarded' => 0,
                'message' => 'Apple Music is not configured in this environment.',
            ];
        }

        $data = $this->appleMusicService->getRecentlyPlayed($user, 25);
        $items = $data['data'] ?? [];

        $fetched = 0;
        $inserted = 0;
        $skipped = 0;
        $xpAwarded = 0;
        $syncTime = new \DateTimeImmutable();

        foreach ($items as $index => $item) {
            $fetched++;

            if (!is_array($item)) {
                $skipped++;
                continue;
            }

            $providerItemId = (string) ($item['id'] ?? '');
            $attributes = $item['attributes'] ?? null;

            if ($providerItemId === '' || !is_array($attributes)) {
                $skipped++;
                continue;
            }

            if ($this->historyRepository->existsForAccountAndItemId($account, $providerItemId)) {
                $skipped++;
                continue;
            }

            $history = new StreamingPlayHistory();
            $history
                ->setUser($user)
                ->setStreamingAccount($account)
                ->setProvider($account->getProvider())
                ->setProviderItemId($providerItemId)
                ->setProviderType((string) ($item['type'] ?? 'song'))
                ->setItemName((string) ($attributes['name'] ?? 'Unknown track'))
                ->setArtistName($this->nullableString($attributes['artistName'] ?? null))
                ->setAlbumName($this->nullableString($attributes['albumName'] ?? null))
                ->setDurationMs(isset($attributes['durationInMillis']) ? (int) $attributes['durationInMillis'] : null)
                ->setPlayedAt($syncTime->modify(sprintf('-%d seconds', $index)))
                ->setRawData($item);

            $this->entityManager->persist($history);
            $transaction = $this->xpEngine->awardStreamingPlay($history);
            $xpAwarded += $transaction?->getXpAmount() ?? 0;
            $inserted++;
        }

        if ($fetched > 0) {
            $account->touchLastSync();
            $this->entityManager->persist($account);
        }

        return [
            'provider' => $account->getProvider(),
            'status' => 'success',
            'fetched' => $fetched,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'xpAwarded' => $xpAwarded,
            'message' => 'Apple Music sync completed from recent played tracks.',
        ];
    }

    private function extractSpotifyArtistName(array $track): ?string
    {
        $artists = $track['artists'] ?? null;

        if (!is_array($artists) || $artists === []) {
            return null;
        }

        $firstArtist = $artists[0] ?? null;

        if (!is_array($firstArtist)) {
            return null;
        }

        $name = $firstArtist['name'] ?? null;

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    private function extractSpotifyAlbumName(array $track): ?string
    {
        $album = $track['album'] ?? null;

        if (!is_array($album)) {
            return null;
        }

        $name = $album['name'] ?? null;

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    private function extractYoutubeChannelName(array $snippet): ?string
    {
        $channelTitle = $snippet['videoOwnerChannelTitle'] ?? $snippet['channelTitle'] ?? null;

        return is_string($channelTitle) && trim($channelTitle) !== '' ? trim($channelTitle) : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
