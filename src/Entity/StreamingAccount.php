<?php

namespace App\Entity;

use App\Repository\StreamingAccountRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StreamingAccountRepository::class)]
#[ORM\Table(name: 'streaming_account')]
#[ORM\UniqueConstraint(name: 'uniq_streaming_provider_user', columns: ['provider', 'provider_user_id'])]
#[ORM\HasLifecycleCallbacks]
class StreamingAccount
{
    public const PROVIDER_SPOTIFY = 'spotify';
    public const PROVIDER_APPLE_MUSIC = 'apple_music';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_ERROR = 'error';
    public const STATUS_DISCONNECTED = 'disconnected';

    public const ALLOWED_PROVIDERS = [
        self::PROVIDER_SPOTIFY,
        self::PROVIDER_APPLE_MUSIC,
    ];

    public const ALLOWED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONNECTED,
        self::STATUS_EXPIRED,
        self::STATUS_ERROR,
        self::STATUS_DISCONNECTED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'streamingAccounts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private ?string $provider = null;

    #[ORM\Column(length: 191, name: 'provider_user_id')]
    private ?string $providerUserId = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $displayName = null;

#[ORM\Column(type: 'text', nullable: true)]
private ?string $accessToken = null;

#[ORM\Column(type: 'text', nullable: true)]
private ?string $refreshToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'json')]
    private array $scopes = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

    #[ORM\Column(length: 30)]
    private string $syncStatus = self::STATUS_PENDING;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->syncStatus = self::STATUS_PENDING;
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

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName !== null ? trim($displayName) : null;

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

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeImmutable $lastSyncAt): self
    {
        $this->lastSyncAt = $lastSyncAt;

        return $this;
    }

    public function touchLastSync(): self
    {
        $this->lastSyncAt = new \DateTimeImmutable();

        return $this;
    }

    public function getSyncStatus(): string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(string $syncStatus): self
    {
        $syncStatus = strtolower(trim($syncStatus));

        if (!in_array($syncStatus, self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Sync status "%s" is not allowed. Allowed statuses: %s',
                $syncStatus,
                implode(', ', self::ALLOWED_STATUSES)
            ));
        }

        $this->syncStatus = $syncStatus;

        return $this;
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt <= new \DateTimeImmutable();
    }

    public function isConnected(): bool
    {
        return $this->syncStatus === self::STATUS_CONNECTED && !$this->isExpired();
    }

    public function expiresSoon(int $seconds = 300): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        $threshold = (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $seconds));

        return $this->expiresAt <= $threshold;
    }

    public function markConnected(): self
    {
        $this->syncStatus = self::STATUS_CONNECTED;

        return $this;
    }

    public function markExpired(): self
    {
        $this->syncStatus = self::STATUS_EXPIRED;

        return $this;
    }

    public function markError(): self
    {
        $this->syncStatus = self::STATUS_ERROR;

        return $this;
    }

    public function markDisconnected(): self
    {
        $this->syncStatus = self::STATUS_DISCONNECTED;

        return $this;
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