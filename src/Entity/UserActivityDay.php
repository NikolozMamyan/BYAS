<?php

namespace App\Entity;

use App\Repository\UserActivityDayRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserActivityDayRepository::class)]
#[ORM\Table(name: 'user_activity_day')]
#[ORM\UniqueConstraint(name: 'uniq_user_activity_day', columns: ['user_id', 'activity_date'])]
class UserActivityDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $activityDate;

    #[ORM\Column(nullable: true)]
    private ?int $globalRank = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->activityDate = new \DateTimeImmutable('today');
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getActivityDate(): \DateTimeImmutable { return $this->activityDate; }
    public function setActivityDate(\DateTimeImmutable $activityDate): self
    {
        $this->activityDate = $activityDate->setTime(0, 0);

        return $this;
    }

    public function getGlobalRank(): ?int { return $this->globalRank; }
    public function setGlobalRank(?int $globalRank): self { $this->globalRank = $globalRank; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
