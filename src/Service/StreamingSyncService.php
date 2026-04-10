<?php

namespace App\Service;

use App\Entity\StreamingAccount;
use App\Entity\StreamingPlayHistory;
use App\Entity\User;
use App\Repository\StreamingAccountRepository;
use App\Repository\StreamingPlayHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class StreamingSyncService
{
    public function __construct(
        private readonly StreamingAccountRepository $accountRepository,
        private readonly StreamingPlayHistoryRepository $historyRepository,
        private readonly SpotifyDataService $spotifyDataService,
        private readonly XpEngine $xpEngine,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function syncUser(User $user): array
    {
        $accounts = $this->accountRepository->findConnectedByUser($user);

        $providers = [];
        $totalFetched = 0;
        $totalInserted = 0;
        $totalSkipped = 0;
        $totalXpAwarded = 0;

        foreach ($accounts as $account) {
            $result = $this->syncAccount($user, $account);

            $providers[] = $result;
            $totalFetched += $result['fetched'];
            $totalInserted += $result['inserted'];
            $totalSkipped += $result['skipped'];
            $totalXpAwarded += $result['xpAwarded'] ?? 0;
        }

        if ($totalInserted > 0) {
            $this->entityManager->flush();
        }

        return [
            'accounts' => count($accounts),
            'totalFetched' => $totalFetched,
            'totalInserted' => $totalInserted,
            'totalSkipped' => $totalSkipped,
            'totalXpAwarded' => $totalXpAwarded,
            'providers' => $providers,
        ];
    }

    private function syncAccount(User $user, StreamingAccount $account): array
    {
        return match ($account->getProvider()) {
            StreamingAccount::PROVIDER_SPOTIFY => $this->syncSpotifyAccount($user, $account),
            StreamingAccount::PROVIDER_APPLE_MUSIC => [
                'provider' => $account->getProvider(),
                'status' => 'not_implemented',
                'fetched' => 0,
                'inserted' => 0,
                'skipped' => 0,
                'xpAwarded' => 0,
                'message' => 'Apple Music sync not implemented yet.',
            ],
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
}
