<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(columns: ['source_type'], name: 'idx_xp_source_type')]
#[ORM\Index(columns: ['occurred_at'], name: 'idx_xp_occurred_at')]
class XpTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'xpTransactions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Artist $artist = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?UserFandom $userFandom = null;

    #[ORM\Column(length: 50, name: 'source_type')]
    private ?string $sourceType = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $sourceReference = null;

    #[ORM\Column]
    private int $xpAmount = 0;

    #[ORM\Column(length: 10)]
    private string $direction = 'credit';

    #[ORM\Column(length: 255)]
    private ?string $reason = null;

    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column(name: 'occurred_at')]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->occurredAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getArtist(): ?Artist { return $this->artist; }
    public function setArtist(?Artist $artist): self { $this->artist = $artist; return $this; }

    public function getUserFandom(): ?UserFandom { return $this->userFandom; }
    public function setUserFandom(?UserFandom $userFandom): self { $this->userFandom = $userFandom; return $this; }

    public function getSourceType(): ?string { return $this->sourceType; }
    public function setSourceType(string $sourceType): self { $this->sourceType = $sourceType; return $this; }

    public function getSourceReference(): ?string { return $this->sourceReference; }
    public function setSourceReference(?string $sourceReference): self { $this->sourceReference = $sourceReference; return $this; }

    public function getXpAmount(): int { return $this->xpAmount; }
    public function setXpAmount(int $xpAmount): self { $this->xpAmount = $xpAmount; return $this; }

    public function getDirection(): string { return $this->direction; }
    public function setDirection(string $direction): self { $this->direction = $direction; return $this; }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(string $reason): self { $this->reason = $reason; return $this; }

    public function getMetadata(): array { return $this->metadata; }
    public function setMetadata(array $metadata): self { $this->metadata = $metadata; return $this; }

    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function setOccurredAt(\DateTimeImmutable $occurredAt): self { $this->occurredAt = $occurredAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}