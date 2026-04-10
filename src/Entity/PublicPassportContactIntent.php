<?php

namespace App\Entity;

use App\Repository\PublicPassportContactIntentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PublicPassportContactIntentRepository::class)]
class PublicPassportContactIntent
{
    public const STATUS_PENDING = 'pending';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?UserProfile $profile = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $sender = null;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 64)]
    private string $source = 'public_passport';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProfile(): ?UserProfile { return $this->profile; }
    public function setProfile(?UserProfile $profile): self { $this->profile = $profile; return $this; }

    public function getSender(): ?User { return $this->sender; }
    public function setSender(?User $sender): self { $this->sender = $sender; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $source): self { $this->source = $source; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
