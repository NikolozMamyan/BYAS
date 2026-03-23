<?php

namespace App\Entity;

use App\Repository\StreamingPlayHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StreamingPlayHistoryRepository::class)]
#[ORM\Table(name: 'streaming_play_history')]
#[ORM\UniqueConstraint(
    name: 'uniq_stream_play',
    columns: ['streaming_account_id', 'provider_item_id', 'played_at']
)]
class StreamingPlayHistory
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
    private ?StreamingAccount $streamingAccount = null;

    #[ORM\Column(length: 50)]
    private ?string $provider = null;

    #[ORM\Column(length: 191)]
    private ?string $providerItemId = null;

    #[ORM\Column(length: 30)]
    private ?string $providerType = null; // track, episode

    #[ORM\Column(length: 255)]
    private ?string $itemName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $artistName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $albumName = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationMs = null;

    #[ORM\Column]
    private \DateTimeImmutable $playedAt;

    #[ORM\Column(type: 'json')]
    private array $rawData = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ===== GETTERS / SETTERS =====

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }

    public function getStreamingAccount(): ?StreamingAccount { return $this->streamingAccount; }
    public function setStreamingAccount(StreamingAccount $account): self { $this->streamingAccount = $account; return $this; }

    public function getProvider(): ?string { return $this->provider; }
    public function setProvider(string $provider): self { $this->provider = $provider; return $this; }

    public function getProviderItemId(): ?string { return $this->providerItemId; }
    public function setProviderItemId(string $id): self { $this->providerItemId = $id; return $this; }

    public function getProviderType(): ?string { return $this->providerType; }
    public function setProviderType(string $type): self { $this->providerType = $type; return $this; }

    public function getItemName(): ?string { return $this->itemName; }
    public function setItemName(string $name): self { $this->itemName = $name; return $this; }

    public function getArtistName(): ?string { return $this->artistName; }
    public function setArtistName(?string $artist): self { $this->artistName = $artist; return $this; }

    public function getAlbumName(): ?string { return $this->albumName; }
    public function setAlbumName(?string $album): self { $this->albumName = $album; return $this; }

    public function getDurationMs(): ?int { return $this->durationMs; }
    public function setDurationMs(?int $duration): self { $this->durationMs = $duration; return $this; }

    public function getPlayedAt(): \DateTimeImmutable { return $this->playedAt; }
    public function setPlayedAt(\DateTimeImmutable $playedAt): self { $this->playedAt = $playedAt; return $this; }

    public function getRawData(): array { return $this->rawData; }
    public function setRawData(array $rawData): self { $this->rawData = $rawData; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}