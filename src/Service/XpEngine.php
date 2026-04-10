<?php

namespace App\Service;

use App\Entity\Artist;
use App\Entity\StreamingPlayHistory;
use App\Entity\User;
use App\Entity\UserFandom;
use App\Entity\XpTransaction;
use App\Repository\XpTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

class XpEngine
{
    public const SOURCE_STREAMING_PLAY = 'streaming_play';

    private const STREAM_XP = 10;
    private const LEVEL_XP_STEP = 100;

    private AsciiSlugger $slugger;

    /**
     * @var array<string, Artist>
     */
    private array $artistsBySlug = [];

    /**
     * @var array<string, UserFandom>
     */
    private array $userFandomsByKey = [];

    /**
     * @var array<string, true>
     */
    private array $xpSourcesAwarded = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly XpTransactionRepository $xpTransactionRepository,
        private readonly BadgeAwarder $badgeAwarder,
    ) {
        $this->slugger = new AsciiSlugger();
    }

    public function awardStreamingPlay(StreamingPlayHistory $history): ?XpTransaction
    {
        $user = $history->getUser();

        if (!$user instanceof User) {
            throw new \InvalidArgumentException('A streaming play must be linked to a user before XP can be awarded.');
        }

        $artist = null;
        if ($history->getArtistName() !== null && trim($history->getArtistName()) !== '') {
            $artist = $this->findOrCreateArtist($history->getArtistName());
        }

        return $this->awardXp(
            user: $user,
            xpAmount: self::STREAM_XP,
            sourceType: self::SOURCE_STREAMING_PLAY,
            sourceReference: $this->buildStreamingReference($history),
            reason: sprintf('Streamed "%s"', $history->getItemName() ?? 'a track'),
            occurredAt: $history->getPlayedAt(),
            artist: $artist,
            metadata: [
                'provider' => $history->getProvider(),
                'providerType' => $history->getProviderType(),
                'providerItemId' => $history->getProviderItemId(),
                'track' => $history->getItemName(),
                'artist' => $history->getArtistName(),
                'album' => $history->getAlbumName(),
                'durationMs' => $history->getDurationMs(),
            ],
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function awardXp(
        User $user,
        int $xpAmount,
        string $sourceType,
        ?string $sourceReference,
        string $reason,
        \DateTimeImmutable $occurredAt,
        ?Artist $artist = null,
        array $metadata = [],
    ): ?XpTransaction {
        if ($xpAmount <= 0) {
            throw new \InvalidArgumentException('XP amount must be greater than 0.');
        }

        if ($this->hasTransactionForSource($sourceType, $sourceReference)) {
            return null;
        }

        $user->setGlobalXp($user->getGlobalXp() + $xpAmount);
        $user->setGlobalLevel($this->levelForXp($user->getGlobalXp()));

        $userFandom = null;
        if ($artist instanceof Artist) {
            $userFandom = $this->findOrCreateUserFandom($user, $artist, $occurredAt);
            $userFandom->setXp($userFandom->getXp() + $xpAmount);
            $userFandom->setLevel($this->levelForXp($userFandom->getXp()));
            $userFandom->setProgressPercent($this->progressPercentForXp($userFandom->getXp()));
            $userFandom->setLastXpAt($occurredAt);
            $userFandom->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($userFandom);
        }

        $this->badgeAwarder->awardForXpEvent(
            $user,
            $sourceType,
            $sourceReference,
            $userFandom,
            $metadata,
        );

        $transaction = new XpTransaction();
        $transaction
            ->setUser($user)
            ->setArtist($artist)
            ->setUserFandom($userFandom)
            ->setSourceType($sourceType)
            ->setSourceReference($sourceReference)
            ->setXpAmount($xpAmount)
            ->setDirection('credit')
            ->setReason($reason)
            ->setMetadata($metadata)
            ->setOccurredAt($occurredAt);

        $this->entityManager->persist($user);
        $this->entityManager->persist($transaction);

        if ($sourceReference !== null && trim($sourceReference) !== '') {
            $this->xpSourcesAwarded[$this->xpSourceCacheKey($sourceType, $sourceReference)] = true;
        }

        return $transaction;
    }

    public function hasTransactionForSource(string $sourceType, ?string $sourceReference): bool
    {
        if ($sourceReference !== null && trim($sourceReference) !== '') {
            $cacheKey = $this->xpSourceCacheKey($sourceType, $sourceReference);

            if (isset($this->xpSourcesAwarded[$cacheKey])) {
                return true;
            }
        }

        return $this->xpTransactionRepository->existsForSource($sourceType, $sourceReference);
    }

    public function sourceReferenceForStreamingPlay(StreamingPlayHistory $history): string
    {
        return $this->buildStreamingReference($history);
    }

    public function levelForXp(int $xp): int
    {
        return max(1, (int) floor(sqrt(max(0, $xp) / self::LEVEL_XP_STEP)) + 1);
    }

    public function progressPercentForXp(int $xp): float
    {
        $level = $this->levelForXp($xp);
        $currentLevelStart = $this->xpRequiredForLevel($level);
        $nextLevelStart = $this->xpRequiredForLevel($level + 1);

        if ($nextLevelStart <= $currentLevelStart) {
            return 100.0;
        }

        return round((($xp - $currentLevelStart) / ($nextLevelStart - $currentLevelStart)) * 100, 2);
    }

    public function xpRequiredForLevel(int $level): int
    {
        $level = max(1, $level);

        return ($level - 1) * ($level - 1) * self::LEVEL_XP_STEP;
    }

    /**
     * @return array{current:int,next:int,percent:float}
     */
    public function progressForXp(int $xp): array
    {
        $level = $this->levelForXp($xp);

        return [
            'current' => $this->xpRequiredForLevel($level),
            'next' => $this->xpRequiredForLevel($level + 1),
            'percent' => $this->progressPercentForXp($xp),
        ];
    }

    private function findOrCreateArtist(string $name): Artist
    {
        $cleanName = trim($name);
        $slug = $this->slugForArtist($cleanName);

        if (isset($this->artistsBySlug[$slug])) {
            return $this->artistsBySlug[$slug];
        }

        $artist = $this->entityManager->getRepository(Artist::class)->findOneBy(['slug' => $slug]);

        if ($artist instanceof Artist) {
            $this->artistsBySlug[$slug] = $artist;

            return $artist;
        }

        $artist = new Artist();
        $artist
            ->setName($cleanName)
            ->setSlug($slug)
            ->setType('artist');

        $this->entityManager->persist($artist);
        $this->artistsBySlug[$slug] = $artist;

        return $artist;
    }

    private function findOrCreateUserFandom(User $user, Artist $artist, \DateTimeImmutable $occurredAt): UserFandom
    {
        $cacheKey = $this->userFandomCacheKey($user, $artist);

        if (isset($this->userFandomsByKey[$cacheKey])) {
            return $this->userFandomsByKey[$cacheKey];
        }

        $userFandom = $this->entityManager->getRepository(UserFandom::class)->findOneBy([
            'user' => $user,
            'artist' => $artist,
        ]);

        if ($userFandom instanceof UserFandom) {
            $this->userFandomsByKey[$cacheKey] = $userFandom;

            return $userFandom;
        }

        $userFandom = new UserFandom();
        $userFandom
            ->setUser($user)
            ->setArtist($artist)
            ->setFirstEngagedAt($occurredAt)
            ->setProgressPercent(0.0);

        $this->userFandomsByKey[$cacheKey] = $userFandom;

        return $userFandom;
    }

    private function slugForArtist(string $name): string
    {
        $slug = strtolower((string) $this->slugger->slug($name));

        return $slug !== '' ? $slug : 'unknown-artist';
    }

    private function userFandomCacheKey(User $user, Artist $artist): string
    {
        $userKey = $user->getId() !== null ? (string) $user->getId() : spl_object_hash($user);
        $artistKey = $artist->getId() !== null ? (string) $artist->getId() : ($artist->getSlug() ?? spl_object_hash($artist));

        return $userKey . ':' . $artistKey;
    }

    private function xpSourceCacheKey(string $sourceType, string $sourceReference): string
    {
        return $sourceType . ':' . $sourceReference;
    }

    private function buildStreamingReference(StreamingPlayHistory $history): string
    {
        return sprintf(
            '%s:%s:%s',
            $history->getProvider() ?? 'streaming',
            $history->getProviderItemId() ?? 'unknown',
            $history->getPlayedAt()->format('U')
        );
    }
}
