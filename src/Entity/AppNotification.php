<?php

namespace App\Entity;

use App\Repository\AppNotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppNotificationRepository::class)]
#[ORM\Index(name: 'idx_app_notification_user_created', columns: ['user_id', 'created_at'])]
#[ORM\Index(name: 'idx_app_notification_user_read', columns: ['user_id', 'is_read'])]
class AppNotification
{
    public const TYPE_PUBLIC_VISIT = 'public_visit';
    public const TYPE_CONTACT_INTENT = 'contact_intent';
    public const TYPE_BADGE_UNLOCKED = 'badge_unlocked';
    public const TYPE_SYNC_COMPLETED = 'sync_completed';
    public const TYPE_PROVIDER_CONNECTED = 'provider_connected';
    public const TYPE_RANK_IMPROVED = 'rank_improved';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actorUser = null;

    #[ORM\Column(length: 64)]
    private string $type;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(length: 255)]
    private string $body;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetUrl = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $actorName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actorAvatarUrl = null;

    #[ORM\Column(type: 'json')]
    private array $contextData = [];

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getActorUser(): ?User
    {
        return $this->actorUser;
    }

    public function setActorUser(?User $actorUser): self
    {
        $this->actorUser = $actorUser;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = trim($type);

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = trim($body);

        return $this;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): self
    {
        $this->targetUrl = $targetUrl !== null ? trim($targetUrl) : null;

        return $this;
    }

    public function getActorName(): ?string
    {
        return $this->actorName;
    }

    public function setActorName(?string $actorName): self
    {
        $this->actorName = $actorName !== null ? trim($actorName) : null;

        return $this;
    }

    public function getActorAvatarUrl(): ?string
    {
        return $this->actorAvatarUrl;
    }

    public function setActorAvatarUrl(?string $actorAvatarUrl): self
    {
        $this->actorAvatarUrl = $actorAvatarUrl !== null ? trim($actorAvatarUrl) : null;

        return $this;
    }

    public function getContextData(): array
    {
        return $this->contextData;
    }

    public function setContextData(array $contextData): self
    {
        $this->contextData = $contextData;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;
        $this->readAt = $isRead ? ($this->readAt ?? new \DateTimeImmutable()) : null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }
}
