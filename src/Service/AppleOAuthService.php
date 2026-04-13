<?php

namespace App\Service;

use App\Entity\OAuthAccount;
use App\Entity\User;
use App\Repository\OAuthAccountRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AppleOAuthService
{
    private const SESSION_STATE_KEY = 'apple_oauth_states';
    private const AUTHORIZE_URL = 'https://appleid.apple.com/auth/authorize';
    private const TOKEN_URL = 'https://appleid.apple.com/auth/token';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly OAuthAccountRepository $oauthAccountRepository,
        private readonly string $appleSignInTeamId,
        private readonly string $appleSignInKeyId,
        private readonly string $appleSignInPrivateKey,
        private readonly string $appleSignInClientId,
        private readonly string $appleSignInRedirectUri,
    ) {
    }

    public function buildAuthorizationUrl(?string $next = null): string
    {
        $state = bin2hex(random_bytes(32));
        $session = $this->requestStack->getSession();
        $states = $session->get(self::SESSION_STATE_KEY, []);
        $states[$state] = [
            'next' => $next,
        ];
        $session->set(self::SESSION_STATE_KEY, $states);

        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->appleSignInClientId,
            'redirect_uri' => $this->appleSignInRedirectUri,
            'response_type' => 'code',
            'response_mode' => 'form_post',
            'scope' => 'name email',
            'state' => $state,
        ]);
    }

    /**
     * @param array<string, mixed>|null $appleUserPayload
     * @return array{user: User, next: ?string}
     */
    public function handleCallback(string $code, ?string $state, ?array $appleUserPayload = null): array
    {
        $payload = $this->consumeStatePayload($state);
        $tokenData = $this->exchangeCodeForTokens($code);
        $claims = $this->decodeIdToken((string) ($tokenData['id_token'] ?? ''));
        $user = $this->findOrCreateUserFromApple($claims, $tokenData, $appleUserPayload);

        return [
            'user' => $user,
            'next' => isset($payload['next']) && is_string($payload['next']) ? $payload['next'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $tokenData
     * @param array<string, mixed>|null $appleUserPayload
     */
    public function findOrCreateUserFromApple(array $claims, array $tokenData, ?array $appleUserPayload = null): User
    {
        $providerUserId = trim((string) ($claims['sub'] ?? ''));
        $email = $this->normalizeNullableString($claims['email'] ?? null);

        if ($providerUserId === '') {
            throw new \RuntimeException('Apple account id missing.');
        }

        $existingOauth = $this->oauthAccountRepository
            ->findOneByProviderAndProviderUserId(OAuthAccount::PROVIDER_APPLE, $providerUserId);

        if ($existingOauth instanceof OAuthAccount && $existingOauth->getUser() instanceof User) {
            $this->upsertAppleAccount($existingOauth->getUser(), $claims, $tokenData);

            return $existingOauth->getUser();
        }

        $user = $email !== null ? $this->userRepository->findOneBy(['email' => $email]) : null;

        if (!$user instanceof User) {
            $firstName = $this->normalizeNullableString($appleUserPayload['name']['firstName'] ?? null);
            $lastName = $this->normalizeNullableString($appleUserPayload['name']['lastName'] ?? null);

            $displayName = trim(implode(' ', array_filter([$firstName, $lastName])));
            if ($displayName === '') {
                $displayName = $email ?? 'Apple Fan';
            }

            $user = (new User())
                ->setEmail($email ?? sprintf('%s@apple.local', $providerUserId))
                ->setDisplayName($displayName)
                ->setFirstName($firstName)
                ->setLastName($lastName)
                ->setRoles(['ROLE_USER'])
                ->setPassword(null);

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $this->upsertAppleAccount($user, $claims, $tokenData);

        return $user;
    }

    /**
     * @param array<string, mixed> $claims
     * @param array<string, mixed> $tokenData
     */
    private function upsertAppleAccount(User $user, array $claims, array $tokenData): OAuthAccount
    {
        $providerUserId = trim((string) ($claims['sub'] ?? ''));

        if ($providerUserId === '') {
            throw new \RuntimeException('Apple account id missing.');
        }

        $account = $this->oauthAccountRepository
            ->findOneByProviderAndProviderUserId(OAuthAccount::PROVIDER_APPLE, $providerUserId);

        if (!$account instanceof OAuthAccount) {
            $account = $this->oauthAccountRepository
                ->findOneByUserAndProvider($user, OAuthAccount::PROVIDER_APPLE) ?? new OAuthAccount();
        }

        if ($account->getUser() instanceof User && $account->getUser()->getId() !== $user->getId()) {
            throw new \RuntimeException('This Apple account is already linked to another user.');
        }

        $refreshToken = $tokenData['refresh_token'] ?? $account->getRefreshToken();
        $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);

        $account
            ->setUser($user)
            ->setProvider(OAuthAccount::PROVIDER_APPLE)
            ->setProviderUserId($providerUserId)
            ->setEmail($this->normalizeNullableString($claims['email'] ?? null))
            ->setAccessToken($this->normalizeNullableString($tokenData['access_token'] ?? null))
            ->setRefreshToken(is_string($refreshToken) ? $refreshToken : null)
            ->setExpiresAt((new \DateTimeImmutable())->modify(sprintf('+%d seconds', $expiresIn)))
            ->setScopes(['name', 'email']);

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCodeForTokens(string $code): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'client_id' => $this->appleSignInClientId,
                'client_secret' => $this->createClientSecret(),
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->appleSignInRedirectUri,
            ],
        ]);

        $data = $response->toArray(false);

        if ($response->getStatusCode() >= 400 || !isset($data['id_token'])) {
            $error = $data['error'] ?? 'Unknown Apple token error.';
            throw new \RuntimeException(sprintf('Apple token exchange failed: %s', (string) $error));
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeIdToken(string $idToken): array
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid Apple id_token.');
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid Apple id_token payload.');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function consumeStatePayload(?string $state): array
    {
        if (!is_string($state) || trim($state) === '') {
            throw new \RuntimeException('Invalid Apple OAuth state.');
        }

        $session = $this->requestStack->getSession();
        $states = $session->get(self::SESSION_STATE_KEY, []);
        $payload = $states[$state] ?? null;

        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid Apple OAuth state.');
        }

        unset($states[$state]);
        $session->set(self::SESSION_STATE_KEY, $states);

        return $payload;
    }

    private function createClientSecret(): string
    {
        $teamId = trim($this->appleSignInTeamId);
        $keyId = trim($this->appleSignInKeyId);
        $clientId = trim($this->appleSignInClientId);
        $privateKey = $this->normalizePrivateKey($this->appleSignInPrivateKey);

        if ($teamId === '' || $keyId === '' || $clientId === '' || $privateKey === '') {
            throw new \RuntimeException('Apple Sign In credentials are missing.');
        }

        $issuedAt = time();
        $expiresAt = $issuedAt + 300;

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'ES256',
            'kid' => $keyId,
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $teamId,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'aud' => 'https://appleid.apple.com',
            'sub' => $clientId,
        ], JSON_THROW_ON_ERROR));

        $input = $header . '.' . $payload;
        $signature = '';
        $result = openssl_sign($input, $signature, $privateKey, 'sha256');

        if ($result !== true || $signature === '') {
            throw new \RuntimeException('Unable to sign Apple client secret.');
        }

        return $input . '.' . $this->base64UrlEncode($this->convertDerSignatureToJose($signature, 64));
    }

    private function normalizePrivateKey(string $privateKey): string
    {
        $privateKey = trim($privateKey);

        if ($privateKey === '') {
            return '';
        }

        if (str_contains($privateKey, '-----BEGIN PRIVATE KEY-----')) {
            return str_replace('\n', "\n", $privateKey);
        }

        $decoded = base64_decode($privateKey, true);

        if (is_string($decoded) && str_contains($decoded, '-----BEGIN PRIVATE KEY-----')) {
            return $decoded;
        }

        return str_replace('\n', "\n", $privateKey);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }

    private function convertDerSignatureToJose(string $derSignature, int $partLength): string
    {
        $offset = 3;
        $rLength = ord($derSignature[$offset]);
        $offset += 1;
        $r = substr($derSignature, $offset, $rLength);
        $offset += $rLength + 1;
        $sLength = ord($derSignature[$offset]);
        $offset += 1;
        $s = substr($derSignature, $offset, $sLength);

        return str_pad(ltrim($r, "\x00"), $partLength / 2, "\x00", STR_PAD_LEFT)
            . str_pad(ltrim($s, "\x00"), $partLength / 2, "\x00", STR_PAD_LEFT);
    }
}
