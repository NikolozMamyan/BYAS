<?php

namespace App\Service;

use App\Entity\OAuthAccount;
use App\Entity\StreamingAccount;
use App\Entity\User;
use App\Repository\OAuthAccountRepository;
use App\Repository\StreamingAccountRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleOAuthService
{
    private const SESSION_STATE_KEY = 'google_oauth_states';

    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const YOUTUBE_ME_URL = 'https://www.googleapis.com/youtube/v3/channels?part=id,snippet,contentDetails&mine=true';

    public const PURPOSE_LOGIN = 'login';
    public const PURPOSE_CONNECT_YOUTUBE = 'connect_youtube';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly OAuthAccountRepository $oauthAccountRepository,
        private readonly StreamingAccountRepository $streamingAccountRepository,
        private readonly string $googleClientId,
        private readonly string $googleClientSecret,
        private readonly string $googleRedirectUri,
    ) {
    }

    public function getLoginScopes(): array
    {
        return ['openid', 'email', 'profile'];
    }

    public function getYoutubeScopes(): array
    {
        return array_values(array_unique([
            ...$this->getLoginScopes(),
            'https://www.googleapis.com/auth/youtube.readonly',
        ]));
    }

    public function buildAuthorizationUrl(string $purpose, ?string $next = null, ?int $userId = null): string
    {
        $state = bin2hex(random_bytes(32));
        $payload = [
            'purpose' => $purpose,
            'next' => $next,
            'userId' => $userId,
        ];

        $session = $this->requestStack->getSession();
        $states = $session->get(self::SESSION_STATE_KEY, []);
        $states[$state] = $payload;
        $session->set(self::SESSION_STATE_KEY, $states);

        $scopes = match ($purpose) {
            self::PURPOSE_CONNECT_YOUTUBE => $this->getYoutubeScopes(),
            default => $this->getLoginScopes(),
        };

        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->googleClientId,
            'redirect_uri' => $this->googleRedirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
        ]);
    }

    public function consumeCallback(string $code, ?string $state): array
    {
        $payload = $this->consumeStatePayload($state);
        $tokenData = $this->exchangeCodeForTokens($code);
        $profile = $this->fetchGoogleProfile((string) $tokenData['access_token']);

        return [
            'payload' => $payload,
            'tokenData' => $tokenData,
            'profile' => $profile,
        ];
    }

    public function findOrCreateUserFromGoogle(array $profile, array $tokenData): User
    {
        $providerUserId = trim((string) ($profile['sub'] ?? ''));
        $email = $this->normalizeNullableString($profile['email'] ?? null);

        if ($providerUserId === '') {
            throw new \RuntimeException('Google account id missing.');
        }

        $existingOauth = $this->oauthAccountRepository
            ->findOneByProviderAndProviderUserId(OAuthAccount::PROVIDER_GOOGLE, $providerUserId);

        if ($existingOauth instanceof OAuthAccount && $existingOauth->getUser() instanceof User) {
            $this->upsertGoogleAccount($existingOauth->getUser(), $profile, $tokenData);

            return $existingOauth->getUser();
        }

        $user = $email !== null ? $this->userRepository->findOneBy(['email' => $email]) : null;

        if (!$user instanceof User) {
            $user = (new User())
                ->setEmail($email ?? sprintf('%s@google.local', $providerUserId))
                ->setDisplayName($this->resolveDisplayName($profile))
                ->setFirstName($this->normalizeNullableString($profile['given_name'] ?? null))
                ->setLastName($this->normalizeNullableString($profile['family_name'] ?? null))
                ->setRoles(['ROLE_USER'])
                ->setPassword(null);

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $this->upsertGoogleAccount($user, $profile, $tokenData);

        return $user;
    }

    public function connectYoutubeForUser(User $user, array $profile, array $tokenData): StreamingAccount
    {
        $this->upsertGoogleAccount($user, $profile, $tokenData);

        $channel = $this->fetchYoutubeChannel((string) $tokenData['access_token']);
        $providerUserId = $this->normalizeNullableString($channel['id'] ?? null)
            ?? $this->normalizeNullableString($profile['sub'] ?? null);

        if ($providerUserId === null) {
            throw new \RuntimeException('YouTube channel id missing.');
        }

        $account = $this->streamingAccountRepository
            ->findOneByProviderAndProviderUserId(StreamingAccount::PROVIDER_YOUTUBE, $providerUserId);

        if (!$account instanceof StreamingAccount) {
            $account = (new StreamingAccount())
                ->setUser($user)
                ->setProvider(StreamingAccount::PROVIDER_YOUTUBE)
                ->setProviderUserId($providerUserId);
        } elseif ($account->getUser()?->getId() !== $user->getId()) {
            throw new \RuntimeException('This YouTube account is already linked to another user.');
        }

        $refreshToken = $tokenData['refresh_token'] ?? $account->getRefreshToken();
        $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

        $account
            ->setDisplayName($this->resolveYoutubeDisplayName($channel, $profile))
            ->setAccessToken((string) ($tokenData['access_token'] ?? ''))
            ->setRefreshToken(is_string($refreshToken) ? $refreshToken : null)
            ->setExpiresAt((new \DateTimeImmutable())->modify(sprintf('+%d seconds', $expiresIn)))
            ->setScopes($this->extractScopes($tokenData, $this->getYoutubeScopes()))
            ->markConnected()
            ->touchLastSync();

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }

    public function refreshStreamingAccessToken(StreamingAccount $account): StreamingAccount
    {
        if ($account->getProvider() !== StreamingAccount::PROVIDER_YOUTUBE) {
            throw new \InvalidArgumentException('StreamingAccount provider must be youtube.');
        }

        if (!$account->getRefreshToken()) {
            throw new \RuntimeException('No Google refresh token available.');
        }

        $data = $this->refreshTokens($account->getRefreshToken());

        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        $newRefreshToken = $data['refresh_token'] ?? $account->getRefreshToken();

        $account
            ->setAccessToken((string) ($data['access_token'] ?? ''))
            ->setRefreshToken(is_string($newRefreshToken) ? $newRefreshToken : null)
            ->setExpiresAt((new \DateTimeImmutable())->modify(sprintf('+%d seconds', $expiresIn)))
            ->setScopes($this->extractScopes($data, $account->getScopes()))
            ->markConnected();

        $this->entityManager->flush();

        return $account;
    }

    public function ensureFreshStreamingAccessToken(StreamingAccount $account): string
    {
        if ($account->isExpired() || $account->expiresSoon()) {
            $account = $this->refreshStreamingAccessToken($account);
        }

        $accessToken = $account->getAccessToken();

        if (!is_string($accessToken) || trim($accessToken) === '') {
            throw new \RuntimeException('Google access token is missing.');
        }

        return $accessToken;
    }

    public function disconnectYoutube(User $user): void
    {
        $account = $this->streamingAccountRepository->findOneByUserAndProvider($user, StreamingAccount::PROVIDER_YOUTUBE);

        if (!$account instanceof StreamingAccount) {
            return;
        }

        $account
            ->clearTokens()
            ->markDisconnected();

        $this->entityManager->flush();
    }

    public function fetchCurrentYoutubeChannel(StreamingAccount $account): array
    {
        $accessToken = $this->ensureFreshStreamingAccessToken($account);

        return $this->fetchYoutubeChannel($accessToken);
    }

    private function upsertGoogleAccount(User $user, array $profile, array $tokenData): OAuthAccount
    {
        $providerUserId = trim((string) ($profile['sub'] ?? ''));

        if ($providerUserId === '') {
            throw new \RuntimeException('Google account id missing.');
        }

        $account = $this->oauthAccountRepository
            ->findOneByProviderAndProviderUserId(OAuthAccount::PROVIDER_GOOGLE, $providerUserId);

        if (!$account instanceof OAuthAccount) {
            $account = $this->oauthAccountRepository
                ->findOneByUserAndProvider($user, OAuthAccount::PROVIDER_GOOGLE) ?? new OAuthAccount();
        }

        if ($account->getUser() instanceof User && $account->getUser()->getId() !== $user->getId()) {
            throw new \RuntimeException('This Google account is already linked to another user.');
        }

        $refreshToken = $tokenData['refresh_token'] ?? $account->getRefreshToken();
        $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

        $account
            ->setUser($user)
            ->setProvider(OAuthAccount::PROVIDER_GOOGLE)
            ->setProviderUserId($providerUserId)
            ->setEmail($this->normalizeNullableString($profile['email'] ?? null))
            ->setAccessToken((string) ($tokenData['access_token'] ?? ''))
            ->setRefreshToken(is_string($refreshToken) ? $refreshToken : null)
            ->setExpiresAt((new \DateTimeImmutable())->modify(sprintf('+%d seconds', $expiresIn)))
            ->setScopes($this->extractScopes($tokenData, $this->getLoginScopes()));

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }

    private function consumeStatePayload(?string $state): array
    {
        if (!is_string($state) || trim($state) === '') {
            throw new \RuntimeException('Invalid Google OAuth state.');
        }

        $session = $this->requestStack->getSession();
        $states = $session->get(self::SESSION_STATE_KEY, []);
        $payload = $states[$state] ?? null;

        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid Google OAuth state.');
        }

        unset($states[$state]);
        $session->set(self::SESSION_STATE_KEY, $states);

        return $payload;
    }

    private function exchangeCodeForTokens(string $code): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'code' => $code,
                'client_id' => $this->googleClientId,
                'client_secret' => $this->googleClientSecret,
                'redirect_uri' => $this->googleRedirectUri,
                'grant_type' => 'authorization_code',
            ],
        ]);

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400 || !isset($data['access_token'])) {
            $error = $data['error_description'] ?? $data['error'] ?? 'Unknown Google token error.';
            throw new \RuntimeException(sprintf('Google token exchange failed: %s', $error));
        }

        return $data;
    }

    private function refreshTokens(string $refreshToken): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'client_id' => $this->googleClientId,
                'client_secret' => $this->googleClientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ],
        ]);

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400 || !isset($data['access_token'])) {
            $error = $data['error_description'] ?? $data['error'] ?? 'Unknown Google refresh error.';
            throw new \RuntimeException(sprintf('Google token refresh failed: %s', $error));
        }

        return $data;
    }

    private function fetchGoogleProfile(string $accessToken): array
    {
        $response = $this->httpClient->request('GET', self::USERINFO_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ]);

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('Unable to fetch Google profile.');
        }

        return $data;
    }

    private function fetchYoutubeChannel(string $accessToken): array
    {
        $response = $this->httpClient->request('GET', self::YOUTUBE_ME_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ]);

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400) {
            $error = $data['error']['message'] ?? 'Unable to fetch YouTube channel.';
            throw new \RuntimeException((string) $error);
        }

        $items = $data['items'] ?? [];

        if (!is_array($items) || $items === [] || !is_array($items[0])) {
            throw new \RuntimeException('No YouTube channel available for this Google account.');
        }

        return $items[0];
    }

    private function resolveDisplayName(array $profile): string
    {
        $name = $this->normalizeNullableString($profile['name'] ?? null);

        if ($name !== null) {
            return $name;
        }

        $email = $this->normalizeNullableString($profile['email'] ?? null);

        if ($email !== null) {
            return $email;
        }

        return 'Google Fan';
    }

    private function resolveYoutubeDisplayName(array $channel, array $profile): string
    {
        $title = $this->normalizeNullableString($channel['snippet']['title'] ?? null);

        return $title ?? $this->resolveDisplayName($profile);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function extractScopes(array $tokenData, array $fallback = []): array
    {
        $scopeString = $tokenData['scope'] ?? null;

        if (!is_string($scopeString) || trim($scopeString) === '') {
            return $fallback;
        }

        $scopes = preg_split('/\s+/', trim($scopeString)) ?: [];

        return array_values(array_unique(array_filter($scopes, static fn ($scope): bool => is_string($scope) && trim($scope) !== '')));
    }
}
