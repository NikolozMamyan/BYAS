<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_user_badge', columns: ['user_id', 'badge_id'])]
class UserBadge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Badge $badge = null;

    #[ORM\Column]
    private \DateTimeImmutable $awardedAt;

    #[ORM\Column(type: 'json')]
    private array $contextData = [];

    public function __construct()
    {
        $this->awardedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getBadge(): ?Badge { return $this->badge; }
    public function setBadge(?Badge $badge): self { $this->badge = $badge; return $this; }

    public function getAwardedAt(): \DateTimeImmutable { return $this->awardedAt; }
    public function setAwardedAt(\DateTimeImmutable $awardedAt): self { $this->awardedAt = $awardedAt; return $this; }

    public function getContextData(): array { return $this->contextData; }
    public function setContextData(array $contextData): self { $this->contextData = $contextData; return $this; }
}