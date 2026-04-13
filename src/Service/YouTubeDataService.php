<?php

namespace App\Service;

use App\Entity\StreamingAccount;
use App\Entity\User;

class YouTubeDataService
{
    private const PLAYLIST_ITEMS_URL = 'https://www.googleapis.com/youtube/v3/playlistItems';

    public function __construct(
        private readonly GoogleOAuthService $googleOAuthService,
        private readonly \Symfony\Contracts\HttpClient\HttpClientInterface $httpClient,
    ) {
    }

    public function getLikedVideos(User $user, int $maxResults = 50): array
    {
        $account = $user->getStreamingAccountByProvider(StreamingAccount::PROVIDER_YOUTUBE);

        if (!$account instanceof StreamingAccount) {
            throw new \RuntimeException('YouTube account not connected.');
        }

        $channel = $this->googleOAuthService->fetchCurrentYoutubeChannel($account);
        $likesPlaylistId = $channel['contentDetails']['relatedPlaylists']['likes'] ?? null;

        if (!is_string($likesPlaylistId) || trim($likesPlaylistId) === '') {
            return ['items' => []];
        }

        $accessToken = $this->googleOAuthService->ensureFreshStreamingAccessToken($account);

        $response = $this->httpClient->request('GET', self::PLAYLIST_ITEMS_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
            'query' => [
                'part' => 'snippet,contentDetails',
                'playlistId' => $likesPlaylistId,
                'maxResults' => max(1, min(50, $maxResults)),
            ],
        ]);

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400) {
            $error = $data['error']['message'] ?? 'YouTube API request failed.';
            throw new \RuntimeException((string) $error);
        }

        return $data;
    }
}
