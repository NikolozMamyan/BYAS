<?php

namespace App\Entity;

use App\Repository\OAuthAccountRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OAuthAccountRepository::class)]
#[ORM\Table(name: 'oauth_account')]
#[ORM\UniqueConstraint(name: 'uniq_oauth_provider_user', columns: ['provider', 'provider_user_id'])]
#[ORM\HasLifecycleCallbacks]
class OAuthAccount
{
    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_APPLE = 'apple';

    public const ALLOWED_PROVIDERS = [
        self::PROVIDER_GOOGLE,
        self::PROVIDER_APPLE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'oauthAccounts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private ?string $provider = null;

    #[ORM\Column(length: 191, name: 'provider_user_id')]
    private ?string $providerUserId = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accessToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $refreshToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'json')]
    private array $scopes = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();

        if (!isset($this->createdAt)) {
            $this->createdAt = $now;
        }

        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $provider = strtolower(trim($provider));

        if (!in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Provider "%s" is not allowed. Allowed providers: %s',
                $provider,
                implode(', ', self::ALLOWED_PROVIDERS)
            ));
        }

        $this->provider = $provider;

        return $this;
    }

    public function getProviderUserId(): ?string
    {
        return $this->providerUserId;
    }

    public function setProviderUserId(string $providerUserId): self
    {
        $providerUserId = trim($providerUserId);

        if ($providerUserId == '') {
            throw new \InvalidArgumentException('providerUserId cannot be empty.');
        }

        $this->providerUserId = $providerUserId;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email !== null ? mb_strtolower(trim($email)) : null;

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function setScopes(array $scopes): self
    {
        $cleanScopes = [];

        foreach ($scopes as $scope) {
            if (!is_string($scope)) {
                continue;
            }

            $scope = trim($scope);

            if ($scope !== '') {
                $cleanScopes[] = $scope;
            }
        }

        $this->scopes = array_values(array_unique($cleanScopes));

        return $this;
    }

    public function addScope(string $scope): self
    {
        $scope = trim($scope);

        if ($scope === '') {
            return $this;
        }

        $scopes = $this->scopes;

        if (!in_array($scope, $scopes, true)) {
            $scopes[] = $scope;
        }

        $this->scopes = array_values($scopes);

        return $this;
    }

    public function removeScope(string $scope): self
    {
        $this->scopes = array_values(array_filter(
            $this->scopes,
            static fn (string $existingScope): bool => $existingScope !== $scope
        ));

        return $this;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt <= new \DateTimeImmutable();
    }

    public function expiresSoon(int $seconds = 300): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        $threshold = (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $seconds));

        return $this->expiresAt <= $threshold;
    }

    public function clearTokens(): self
    {
        $this->accessToken = null;
        $this->refreshToken = null;
        $this->expiresAt = null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}