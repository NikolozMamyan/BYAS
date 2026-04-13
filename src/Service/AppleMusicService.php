<?php

namespace App\Service;

use App\Entity\StreamingAccount;
use App\Entity\User;
use App\Repository\StreamingAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AppleMusicService
{
    private const RECENT_TRACKS_URL = 'https://api.music.apple.com/v1/me/recent/played/tracks';

    public function __construct(
        private readonly AppleMusicTokenService $appleMusicTokenService,
        private readonly StreamingAccountRepository $streamingAccountRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getDeveloperToken(): string
    {
        return $this->appleMusicTokenService->createDeveloperToken();
    }

    public function getAppName(): string
    {
        return $this->appleMusicTokenService->getAppName();
    }

    public function connect(User $user, string $musicUserToken, ?string $storefrontId = null): StreamingAccount
    {
        $musicUserToken = trim($musicUserToken);

        if ($musicUserToken === '') {
            throw new \RuntimeException('Apple Music user token is missing.');
        }

        $account = $this->streamingAccountRepository
            ->findOneByUserAndProvider($user, StreamingAccount::PROVIDER_APPLE_MUSIC);

        if (!$account instanceof StreamingAccount) {
            $account = (new StreamingAccount())
                ->setUser($user)
                ->setProvider(StreamingAccount::PROVIDER_APPLE_MUSIC)
                ->setProviderUserId(sprintf('apple-music-user-%d', $user->getId() ?? 0));
        }

        $scopes = ['music-user-token'];

        if (is_string($storefrontId) && trim($storefrontId) !== '') {
            $scopes[] = 'storefront:' . trim($storefrontId);
        }

        $account
            ->setDisplayName('Apple Music')
            ->setAccessToken($musicUserToken)
            ->setRefreshToken(null)
            ->setExpiresAt(null)
            ->setScopes($scopes)
            ->markConnected()
            ->touchLastSync();

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }

    public function disconnect(User $user): void
    {
        $account = $this->streamingAccountRepository
            ->findOneByUserAndProvider($user, StreamingAccount::PROVIDER_APPLE_MUSIC);

        if (!$account instanceof StreamingAccount) {
            return;
        }

        $account
            ->clearTokens()
            ->markDisconnected();

        $this->entityManager->flush();
    }

    public function getRecentlyPlayed(User $user, int $limit = 25): array
    {
        $account = $user->getStreamingAccountByProvider(StreamingAccount::PROVIDER_APPLE_MUSIC);

        if (!$account instanceof StreamingAccount || !$account->isConnected()) {
            throw new \RuntimeException('Apple Music account not connected.');
        }

        $musicUserToken = $account->getAccessToken();

        if (!is_string($musicUserToken) || trim($musicUserToken) === '') {
            throw new \RuntimeException('Apple Music user token is missing.');
        }

        $response = $this->httpClient->request('GET', self::RECENT_TRACKS_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getDeveloperToken(),
                'Music-User-Token' => $musicUserToken,
                'Accept' => 'application/json',
            ],
            'query' => [
                'limit' => max(1, min(50, $limit)),
            ],
        ]);

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400) {
            $error = $data['errors'][0]['detail'] ?? $data['errors'][0]['title'] ?? 'Apple Music API request failed.';
            throw new \RuntimeException((string) $error);
        }

        return $data;
    }
}
