<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Badge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 120)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iconUrl = null;

    #[ORM\Column(length: 30)]
    private string $scope = 'global';

    #[ORM\Column(length: 50)]
    private ?string $ruleType = null;

    #[ORM\Column(type: 'json')]
    private array $ruleConfig = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Artist $artist = null;

    #[ORM\Column]
    private bool $isActive = true;

    public function getId(): ?int { return $this->id; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getIconUrl(): ?string { return $this->iconUrl; }
    public function setIconUrl(?string $iconUrl): self { $this->iconUrl = $iconUrl; return $this; }

    public function getScope(): string { return $this->scope; }
    public function setScope(string $scope): self { $this->scope = $scope; return $this; }

    public function getRuleType(): ?string { return $this->ruleType; }
    public function setRuleType(string $ruleType): self { $this->ruleType = $ruleType; return $this; }

    public function getRuleConfig(): array { return $this->ruleConfig; }
    public function setRuleConfig(array $ruleConfig): self { $this->ruleConfig = $ruleConfig; return $this; }

    public function getArtist(): ?Artist { return $this->artist; }
    public function setArtist(?Artist $artist): self { $this->artist = $artist; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
}