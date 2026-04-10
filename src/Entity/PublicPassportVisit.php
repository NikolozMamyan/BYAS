<?php

namespace App\Entity;

use App\Repository\PublicPassportVisitRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PublicPassportVisitRepository::class)]
class PublicPassportVisit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserProfile $profile = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $viewer = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ipHash = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $userAgentHash = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referrer = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProfile(): ?UserProfile { return $this->profile; }
    public function setProfile(?UserProfile $profile): self { $this->profile = $profile; return $this; }

    public function getViewer(): ?User { return $this->viewer; }
    public function setViewer(?User $viewer): self { $this->viewer = $viewer; return $this; }

    public function getIpHash(): ?string { return $this->ipHash; }
    public function setIpHash(?string $ipHash): self { $this->ipHash = $ipHash; return $this; }

    public function getUserAgentHash(): ?string { return $this->userAgentHash; }
    public function setUserAgentHash(?string $userAgentHash): self { $this->userAgentHash = $userAgentHash; return $this; }

    public function getReferrer(): ?string { return $this->referrer; }
    public function setReferrer(?string $referrer): self { $this->referrer = $referrer; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
