<?php

namespace App\Service;

use App\Entity\StreamingAccount;
use App\Entity\User;
use App\Repository\StreamingAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SpotifyOAuthService
{
    private const SESSION_STATE_KEY = 'spotify_oauth_state';

    private const AUTHORIZE_URL = 'https://accounts.spotify.com/authorize';
    private const TOKEN_URL = 'https://accounts.spotify.com/api/token';
    private const ME_URL = 'https://api.spotify.com/v1/me';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly StreamingAccountRepository $streamingAccountRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $spotifyClientId,
        private readonly string $spotifyClientSecret,
        private readonly string $spotifyRedirectUri,
    ) {
    }

    /**
     * Scopes minimaux utiles pour BYAS phase 1.
     * Ajuste plus tard selon les endpoints réellement utilisés.
     */
public function getDefaultScopes(): array
{
    return [
        'user-read-email',
        'user-read-private',
        'user-read-recently-played',
        'user-top-read',
        'user-library-read',
        'user-read-playback-position',
    ];
}

    public function buildAuthorizationUrl(?array $scopes = null): string
    {
        $scopes ??= $this->getDefaultScopes();

        $state = bin2hex(random_bytes(32));
        $this->requestStack->getSession()->set(self::SESSION_STATE_KEY, $state);

        $query = http_build_query([
            'client_id' => $this->spotifyClientId,
            'response_type' => 'code',
            'redirect_uri' => $this->spotifyRedirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'show_dialog' => 'true',
        ]);

        return self::AUTHORIZE_URL . '?' . $query;
    }

    public function handleCallback(User $user, string $code, ?string $state): StreamingAccount
    {
        $this->assertValidState($state);

        $tokenData = $this->exchangeCodeForTokens($code);
        $profileData = $this->fetchCurrentUserProfile($tokenData['access_token']);

        $spotifyUserId = (string) ($profileData['id'] ?? '');
        if ($spotifyUserId === '') {
            throw new \RuntimeException('Spotify user id missing in profile response.');
        }

        $streamingAccount = $this->streamingAccountRepository
            ->findOneByProviderAndProviderUserId(StreamingAccount::PROVIDER_SPOTIFY, $spotifyUserId);

        if (!$streamingAccount instanceof StreamingAccount) {
            $streamingAccount = new StreamingAccount();
            $streamingAccount
                ->setUser($user)
                ->setProvider(StreamingAccount::PROVIDER_SPOTIFY)
                ->setProviderUserId($spotifyUserId);
        } elseif ($streamingAccount->getUser()?->getId() !== $user->getId()) {
            throw new \RuntimeException('This Spotify account is already linked to another user.');
        }

        $refreshToken = $tokenData['refresh_token'] ?? $streamingAccount->getRefreshToken();
        $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

        $streamingAccount
            ->setDisplayName($this->extractDisplayName($profileData))
            ->setAccessToken((string) $tokenData['access_token'])
            ->setRefreshToken($refreshToken !== null ? (string) $refreshToken : null)
            ->setExpiresAt((new \DateTimeImmutable())->modify(sprintf('+%d seconds', $expiresIn)))
            ->setScopes($this->extractScopes($tokenData))
            ->setSyncStatus(StreamingAccount::STATUS_CONNECTED)
            ->touchLastSync();

        $this->entityManager->persist($streamingAccount);
        $this->entityManager->flush();

        return $streamingAccount;
    }

    public function refreshAccessToken(StreamingAccount $streamingAccount): StreamingAccount
    {
        if ($streamingAccount->getProvider() !== StreamingAccount::PROVIDER_SPOTIFY) {
            throw new \InvalidArgumentException('StreamingAccount provider must be spotify.');
        }

        if (!$streamingAccount->getRefreshToken()) {
            throw new \RuntimeException('No Spotify refresh token available.');
        }

        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(sprintf(
                    '%s:%s',
                    $this->spotifyClientId,
                    $this->spotifyClientSecret
                )),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $streamingAccount->getRefreshToken(),
            ],
        ]);

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400 || !isset($data['access_token'])) {
            $streamingAccount->markExpired();
            $this->entityManager->flush();

            throw new \RuntimeException('Unable to refresh Spotify access token.');
        }

        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        $newRefreshToken = $data['refresh_token'] ?? $streamingAccount->getRefreshToken();

        $streamingAccount
            ->setAccessToken((string) $data['access_token'])
            ->setRefreshToken($newRefreshToken !== null ? (string) $newRefreshToken : null)
            ->setExpiresAt((new \DateTimeImmutable())->modify(sprintf('+%d seconds', $expiresIn)))
            ->setScopes($this->extractScopes($data, $streamingAccount->getScopes()))
            ->markConnected();

        $this->entityManager->flush();

        return $streamingAccount;
    }

    public function ensureFreshAccessToken(StreamingAccount $streamingAccount): string
    {
        if ($streamingAccount->isExpired() || $streamingAccount->expiresSoon()) {
            $streamingAccount = $this->refreshAccessToken($streamingAccount);
        }

        $accessToken = $streamingAccount->getAccessToken();
        if ($accessToken === null || $accessToken === '') {
            throw new \RuntimeException('Spotify access token is missing.');
        }

        return $accessToken;
    }

    public function disconnect(User $user): void
    {
        $account = $this->streamingAccountRepository
            ->findOneByUserAndProvider($user, StreamingAccount::PROVIDER_SPOTIFY);

        if (!$account instanceof StreamingAccount) {
            return;
        }

        $account
            ->clearTokens()
            ->markDisconnected();

        $this->entityManager->flush();
    }

    private function assertValidState(?string $state): void
    {
        $expectedState = $this->requestStack->getSession()->get(self::SESSION_STATE_KEY);

        if (!$state || !$expectedState || !hash_equals((string) $expectedState, (string) $state)) {
            throw new \RuntimeException('Invalid Spotify OAuth state.');
        }

        $this->requestStack->getSession()->remove(self::SESSION_STATE_KEY);
    }

    private function exchangeCodeForTokens(string $code): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(sprintf(
                    '%s:%s',
                    $this->spotifyClientId,
                    $this->spotifyClientSecret
                )),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->spotifyRedirectUri,
            ],
        ]);

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400 || !isset($data['access_token'])) {
            $error = $data['error_description'] ?? $data['error'] ?? 'Unknown Spotify token error.';
            throw new \RuntimeException(sprintf('Spotify token exchange failed: %s', $error));
        }

        return $data;
    }

    private function fetchCurrentUserProfile(string $accessToken): array
    {
        $response = $this->httpClient->request('GET', self::ME_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ]);

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('Unable to fetch Spotify profile.');
        }

        return $data;
    }

    private function extractDisplayName(array $profileData): ?string
    {
        $displayName = $profileData['display_name'] ?? null;

        if (is_string($displayName) && trim($displayName) !== '') {
            return trim($displayName);
        }

        $email = $profileData['email'] ?? null;
        if (is_string($email) && trim($email) !== '') {
            return trim($email);
        }

        $id = $profileData['id'] ?? null;
        if (is_string($id) && trim($id) !== '') {
            return trim($id);
        }

        return null;
    }

    private function extractScopes(array $tokenData, array $fallback = []): array
    {
        $scopeString = $tokenData['scope'] ?? null;

        if (!is_string($scopeString) || trim($scopeString) === '') {
            return $fallback;
        }

        $scopes = preg_split('/\s+/', trim($scopeString)) ?: [];

        return array_values(array_unique(array_filter($scopes, static fn ($scope) => is_string($scope) && $scope !== '')));
    }
}