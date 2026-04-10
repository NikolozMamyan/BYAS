<?php

namespace App\Entity;

use App\Entity\OAuthAccount;
use App\Entity\StreamingAccount;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 120)]
    private ?string $displayName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column]
    private int $globalXp = 0;

    #[ORM\Column]
    private int $globalLevel = 1;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: UserProfile::class, cascade: ['persist', 'remove'])]
    private ?UserProfile $profile = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: OAuthAccount::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $oauthAccounts;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: StreamingAccount::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $streamingAccounts;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserFandom::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $userFandoms;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CollectionItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $collectionItems;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: XpTransaction::class, cascade: ['persist'], orphanRemoval: false)]
    private Collection $xpTransactions;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserBadge::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $userBadges;

    public function __construct()
    {
        $this->oauthAccounts = new ArrayCollection();
        $this->streamingAccounts = new ArrayCollection();
        $this->userFandoms = new ArrayCollection();
        $this->xpTransactions = new ArrayCollection();
        $this->collectionItems = new ArrayCollection();
        $this->userBadges = new ArrayCollection();

        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTimeImmutable();

        if (!isset($this->createdAt)) {
            $this->createdAt = $now;
        }

        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') {
            throw new \InvalidArgumentException('Email cannot be empty.');
        }

        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $cleanRoles = [];

        foreach ($roles as $role) {
            if (!is_string($role)) {
                continue;
            }

            $role = strtoupper(trim($role));

            if ($role !== '') {
                $cleanRoles[] = $role;
            }
        }

        $this->roles = array_values(array_unique($cleanRoles));

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password !== null ? trim($password) : null;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $displayName = trim($displayName);

        if ($displayName === '') {
            throw new \InvalidArgumentException('Display name cannot be empty.');
        }

        $this->displayName = $displayName;

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): self
    {
        $this->avatarUrl = $avatarUrl !== null ? trim($avatarUrl) : null;

        return $this;
    }

    public function getGlobalXp(): int
    {
        return $this->globalXp;
    }

    public function setGlobalXp(int $globalXp): self
    {
        $this->globalXp = max(0, $globalXp);

        return $this;
    }

    public function addGlobalXp(int $xp): self
    {
        if ($xp < 0) {
            throw new \InvalidArgumentException('XP to add must be greater than or equal to 0.');
        }

        $this->globalXp += $xp;

        return $this;
    }

    public function removeGlobalXp(int $xp): self
    {
        if ($xp < 0) {
            throw new \InvalidArgumentException('XP to remove must be greater than or equal to 0.');
        }

        $this->globalXp = max(0, $this->globalXp - $xp);

        return $this;
    }

    public function getGlobalLevel(): int
    {
        return $this->globalLevel;
    }

    public function setGlobalLevel(int $globalLevel): self
    {
        $this->globalLevel = max(1, $globalLevel);

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getProfile(): ?UserProfile
    {
        return $this->profile;
    }

    public function setProfile(?UserProfile $profile): self
    {
        $this->profile = $profile;

        if ($profile !== null && $profile->getUser() !== $this) {
            $profile->setUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, OAuthAccount>
     */
    public function getOauthAccounts(): Collection
    {
        return $this->oauthAccounts;
    }

    public function addOauthAccount(OAuthAccount $oauthAccount): self
    {
        if (!$this->oauthAccounts->contains($oauthAccount)) {
            $this->oauthAccounts->add($oauthAccount);
            $oauthAccount->setUser($this);
        }

        return $this;
    }

    public function removeOauthAccount(OAuthAccount $oauthAccount): self
    {
        if ($this->oauthAccounts->removeElement($oauthAccount)) {
            if ($oauthAccount->getUser() === $this) {
                $oauthAccount->setUser(null);
            }
        }

        return $this;
    }

    public function getOauthAccountByProvider(string $provider): ?OAuthAccount
    {
        foreach ($this->oauthAccounts as $oauthAccount) {
            if ($oauthAccount->getProvider() === mb_strtolower(trim($provider))) {
                return $oauthAccount;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, StreamingAccount>
     */
    public function getStreamingAccounts(): Collection
    {
        return $this->streamingAccounts;
    }

    public function addStreamingAccount(StreamingAccount $streamingAccount): self
    {
        if (!$this->streamingAccounts->contains($streamingAccount)) {
            $this->streamingAccounts->add($streamingAccount);
            $streamingAccount->setUser($this);
        }

        return $this;
    }

    public function removeStreamingAccount(StreamingAccount $streamingAccount): self
    {
        if ($this->streamingAccounts->removeElement($streamingAccount)) {
            if ($streamingAccount->getUser() === $this) {
                $streamingAccount->setUser(null);
            }
        }

        return $this;
    }

    public function getStreamingAccountByProvider(string $provider): ?StreamingAccount
    {
        foreach ($this->streamingAccounts as $streamingAccount) {
            if ($streamingAccount->getProvider() === mb_strtolower(trim($provider))) {
                return $streamingAccount;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, UserFandom>
     */
    public function getUserFandoms(): Collection
    {
        return $this->userFandoms;
    }

    public function addUserFandom(UserFandom $userFandom): self
    {
        if (!$this->userFandoms->contains($userFandom)) {
            $this->userFandoms->add($userFandom);
            $userFandom->setUser($this);
        }

        return $this;
    }

    public function removeUserFandom(UserFandom $userFandom): self
    {
        if ($this->userFandoms->removeElement($userFandom)) {
            if ($userFandom->getUser() === $this) {
                $userFandom->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, XpTransaction>
     */
    public function getXpTransactions(): Collection
    {
        return $this->xpTransactions;
    }

    public function addXpTransaction(XpTransaction $xpTransaction): self
    {
        if (!$this->xpTransactions->contains($xpTransaction)) {
            $this->xpTransactions->add($xpTransaction);
            $xpTransaction->setUser($this);
        }

        return $this;
    }

    public function removeXpTransaction(XpTransaction $xpTransaction): self
    {
        if ($this->xpTransactions->removeElement($xpTransaction)) {
            if ($xpTransaction->getUser() === $this) {
                $xpTransaction->setUser(null);
            }
        }

        return $this;
    }
    /**
 * @return Collection<int, CollectionItem>
 */
public function getCollectionItems(): Collection
{
    return $this->collectionItems;
}

public function addCollectionItem(CollectionItem $collectionItem): self
{
    if (!$this->collectionItems->contains($collectionItem)) {
        $this->collectionItems->add($collectionItem);
        $collectionItem->setUser($this);
    }

    return $this;
}

public function removeCollectionItem(CollectionItem $collectionItem): self
{
    if ($this->collectionItems->removeElement($collectionItem)) {
        if ($collectionItem->getUser() === $this) {
            $collectionItem->setUser(null);
        }
    }

    return $this;
}

/**
 * @return Collection<int, UserBadge>
 */
public function getUserBadges(): Collection
{
    return $this->userBadges;
}

public function addUserBadge(UserBadge $userBadge): self
{
    if (!$this->userBadges->contains($userBadge)) {
        $this->userBadges->add($userBadge);
        $userBadge->setUser($this);
    }

    return $this;
}

public function removeUserBadge(UserBadge $userBadge): self
{
    if ($this->userBadges->removeElement($userBadge)) {
        if ($userBadge->getUser() === $this) {
            $userBadge->setUser(null);
        }
    }

    return $this;
}
}
