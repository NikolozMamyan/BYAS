<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'user_fandom')]
#[ORM\UniqueConstraint(name: 'uniq_user_artist', columns: ['user_id', 'artist_id'])]
class UserFandom
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userFandoms')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Artist $artist = null;

    #[ORM\Column]
    private int $xp = 0;

    #[ORM\Column]
    private int $level = 1;

    #[ORM\Column(nullable: true)]
    private ?int $rankPosition = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $rankPercentile = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $progressPercent = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $firstEngagedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastXpAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getArtist(): ?Artist { return $this->artist; }
    public function setArtist(?Artist $artist): self { $this->artist = $artist; return $this; }

    public function getXp(): int { return $this->xp; }
    public function setXp(int $xp): self { $this->xp = $xp; return $this; }

    public function getLevel(): int { return $this->level; }
    public function setLevel(int $level): self { $this->level = $level; return $this; }

    public function getRankPosition(): ?int { return $this->rankPosition; }
    public function setRankPosition(?int $rankPosition): self { $this->rankPosition = $rankPosition; return $this; }

    public function getRankPercentile(): ?float { return $this->rankPercentile; }
    public function setRankPercentile(?float $rankPercentile): self { $this->rankPercentile = $rankPercentile; return $this; }

    public function getProgressPercent(): ?float { return $this->progressPercent; }
    public function setProgressPercent(?float $progressPercent): self { $this->progressPercent = $progressPercent; return $this; }

    public function getFirstEngagedAt(): ?\DateTimeImmutable { return $this->firstEngagedAt; }
    public function setFirstEngagedAt(?\DateTimeImmutable $firstEngagedAt): self { $this->firstEngagedAt = $firstEngagedAt; return $this; }

    public function getLastXpAt(): ?\DateTimeImmutable { return $this->lastXpAt; }
    public function setLastXpAt(?\DateTimeImmutable $lastXpAt): self { $this->lastXpAt = $lastXpAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}