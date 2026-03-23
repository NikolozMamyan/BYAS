<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CollectionItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CollectionItemType $type = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Artist $artist = null;

    #[ORM\Column(length: 180)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column]
    private bool $isPublic = true;

    #[ORM\Column]
    private \DateTimeImmutable $declaredAt;

    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    public function __construct()
    {
        $this->declaredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getType(): ?CollectionItemType { return $this->type; }
    public function setType(?CollectionItemType $type): self { $this->type = $type; return $this; }

    public function getArtist(): ?Artist { return $this->artist; }
    public function setArtist(?Artist $artist): self { $this->artist = $artist; return $this; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): self { $this->quantity = $quantity; return $this; }

    public function isPublic(): bool { return $this->isPublic; }
    public function setIsPublic(bool $isPublic): self { $this->isPublic = $isPublic; return $this; }

    public function getDeclaredAt(): \DateTimeImmutable { return $this->declaredAt; }
    public function setDeclaredAt(\DateTimeImmutable $declaredAt): self { $this->declaredAt = $declaredAt; return $this; }

    public function getMetadata(): array { return $this->metadata; }
    public function setMetadata(array $metadata): self { $this->metadata = $metadata; return $this; }
}