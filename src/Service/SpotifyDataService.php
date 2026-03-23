<?php

namespace App\Service;

use App\Entity\StreamingAccount;
use App\Entity\User;
use App\Repository\StreamingAccountRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpotifyDataService
{
    private const API_BASE_URL = 'https://api.spotify.com/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly StreamingAccountRepository $streamingAccountRepository,
        private readonly SpotifyOAuthService $spotifyOAuthService,
    ) {
    }

    public function getSpotifyAccountForUser(User $user): StreamingAccount
    {
        $account = $this->streamingAccountRepository
            ->findOneByUserAndProvider($user, StreamingAccount::PROVIDER_SPOTIFY);

        if (!$account instanceof StreamingAccount) {
            throw new \RuntimeException('No Spotify account connected for this user.');
        }

        if ($account->getSyncStatus() !== StreamingAccount::STATUS_CONNECTED) {
            throw new \RuntimeException('Spotify account is not connected.');
        }

        return $account;
    }

    public function getCurrentProfile(User $user): array
    {
        $account = $this->getSpotifyAccountForUser($user);

        return $this->request(
            $account,
            'GET',
            '/me'
        );
    }

    public function getRecentlyPlayed(User $user, int $limit = 20, ?string $after = null, ?string $before = null): array
    {
        $account = $this->getSpotifyAccountForUser($user);

        $query = [
            'limit' => max(1, min(50, $limit)),
        ];

        if ($after !== null) {
            $query['after'] = $after;
        }

        if ($before !== null) {
            $query['before'] = $before;
        }

        return $this->request(
            $account,
            'GET',
            '/me/player/recently-played',
            $query
        );
    }

    public function getTopTracks(User $user, string $timeRange = 'medium_term', int $limit = 20, int $offset = 0): array
    {
        return $this->getTopItems($user, 'tracks', $timeRange, $limit, $offset);
    }

    public function getTopArtists(User $user, string $timeRange = 'medium_term', int $limit = 20, int $offset = 0): array
    {
        return $this->getTopItems($user, 'artists', $timeRange, $limit, $offset);
    }

    public function getSavedTracks(User $user, int $limit = 20, int $offset = 0): array
    {
        $account = $this->getSpotifyAccountForUser($user);

        return $this->request(
            $account,
            'GET',
            '/me/tracks',
            [
                'limit' => max(1, min(50, $limit)),
                'offset' => max(0, $offset),
            ]
        );
    }

    public function getSavedEpisodes(User $user, int $limit = 20, int $offset = 0): array
    {
        $account = $this->getSpotifyAccountForUser($user);

        return $this->request(
            $account,
            'GET',
            '/me/episodes',
            [
                'limit' => max(1, min(50, $limit)),
                'offset' => max(0, $offset),
            ]
        );
    }

    public function getSavedShows(User $user, int $limit = 20, int $offset = 0): array
    {
        $account = $this->getSpotifyAccountForUser($user);

        return $this->request(
            $account,
            'GET',
            '/me/shows',
            [
                'limit' => max(1, min(50, $limit)),
                'offset' => max(0, $offset),
            ]
        );
    }

    public function getPlaybackState(User $user): array
    {
        $account = $this->getSpotifyAccountForUser($user);

        return $this->request(
            $account,
            'GET',
            '/me/player'
        );
    }

    private function getTopItems(User $user, string $type, string $timeRange, int $limit, int $offset): array
    {
        if (!in_array($type, ['tracks', 'artists'], true)) {
            throw new \InvalidArgumentException('Top items type must be "tracks" or "artists".');
        }

        $allowedTimeRanges = ['short_term', 'medium_term', 'long_term'];
        if (!in_array($timeRange, $allowedTimeRanges, true)) {
            throw new \InvalidArgumentException('Invalid Spotify time_range.');
        }

        $account = $this->getSpotifyAccountForUser($user);

        return $this->request(
            $account,
            'GET',
            sprintf('/me/top/%s', $type),
            [
                'time_range' => $timeRange,
                'limit' => max(1, min(50, $limit)),
                'offset' => max(0, $offset),
            ]
        );
    }

    private function request(
        StreamingAccount $account,
        string $method,
        string $path,
        array $query = []
    ): array {
        $accessToken = $this->spotifyOAuthService->ensureFreshAccessToken($account);

        $response = $this->httpClient->request($method, self::API_BASE_URL . $path, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
            'query' => $query,
        ]);

        if ($response->getStatusCode() === 204) {
            return [];
        }

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400) {
            $message = $data['error']['message'] ?? 'Spotify API request failed.';
            throw new \RuntimeException($message);
        }

        return $data;
    }
}