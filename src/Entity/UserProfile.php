<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class UserProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'profile')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $username = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column]
    private bool $isProfilePublic = true;

    #[ORM\Column]
    private bool $showGlobalRank = true;

    #[ORM\Column]
    private bool $showCollection = true;

    #[ORM\Column]
    private bool $showBadges = true;

    #[ORM\Column]
    private bool $showFandomLevels = true;

    #[ORM\Column(length: 120, unique: true)]
    private ?string $shareSlug = null;

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

    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string $username): self { $this->username = $username; return $this; }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): self { $this->bio = $bio; return $this; }

    public function getCountryCode(): ?string { return $this->countryCode; }
    public function setCountryCode(?string $countryCode): self { $this->countryCode = $countryCode; return $this; }

    public function isProfilePublic(): bool { return $this->isProfilePublic; }
    public function setIsProfilePublic(bool $isProfilePublic): self { $this->isProfilePublic = $isProfilePublic; return $this; }

    public function isShowGlobalRank(): bool { return $this->showGlobalRank; }
    public function setShowGlobalRank(bool $showGlobalRank): self { $this->showGlobalRank = $showGlobalRank; return $this; }

    public function isShowCollection(): bool { return $this->showCollection; }
    public function setShowCollection(bool $showCollection): self { $this->showCollection = $showCollection; return $this; }

    public function isShowBadges(): bool { return $this->showBadges; }
    public function setShowBadges(bool $showBadges): self { $this->showBadges = $showBadges; return $this; }

    public function isShowFandomLevels(): bool { return $this->showFandomLevels; }
    public function setShowFandomLevels(bool $showFandomLevels): self { $this->showFandomLevels = $showFandomLevels; return $this; }

    public function getShareSlug(): ?string { return $this->shareSlug; }
    public function setShareSlug(string $shareSlug): self { $this->shareSlug = $shareSlug; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}