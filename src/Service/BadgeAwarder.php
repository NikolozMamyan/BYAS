<?php

namespace App\Service;

use App\Entity\Artist;
use App\Entity\Badge;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Entity\UserFandom;
use Doctrine\ORM\EntityManagerInterface;

class BadgeAwarder
{
    /**
     * @var array<string, Badge>
     */
    private array $badgesByCode = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BadgeCatalog $badgeCatalog,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function awardForXpEvent(
        User $user,
        string $sourceType,
        ?string $sourceReference,
        ?UserFandom $userFandom,
        array $context = [],
    ): void {
        if ($sourceType === XpEngine::SOURCE_STREAMING_PLAY) {
            $this->awardIfMissing($user, BadgeCatalog::FIRST_STREAM, [
                'sourceType' => $sourceType,
                'sourceReference' => $sourceReference,
            ] + $context);
        }

        if ($user->getGlobalLevel() >= 10) {
            $this->awardIfMissing($user, BadgeCatalog::GLOBAL_LEVEL_10, [
                'globalXp' => $user->getGlobalXp(),
                'globalLevel' => $user->getGlobalLevel(),
            ] + $context);
        }

        if ($userFandom instanceof UserFandom && $userFandom->getLevel() >= 10) {
            $artist = $userFandom->getArtist();
            $code = $this->artistScopedCode(BadgeCatalog::FANDOM_LEVEL_10, $artist);

            $this->awardIfMissing($user, $code, [
                'baseCode' => BadgeCatalog::FANDOM_LEVEL_10,
                'artistId' => $artist?->getId(),
                'artistName' => $artist?->getName(),
                'fandomXp' => $userFandom->getXp(),
                'fandomLevel' => $userFandom->getLevel(),
            ] + $context, $artist, BadgeCatalog::FANDOM_LEVEL_10);
        }
    }

    /**
     * @param array<string, mixed> $contextData
     */
    private function awardIfMissing(
        User $user,
        string $code,
        array $contextData,
        ?Artist $artist = null,
        ?string $catalogCode = null,
    ): void {
        foreach ($user->getUserBadges() as $userBadge) {
            if ($userBadge->getBadge()?->getCode() === $code) {
                return;
            }
        }

        $badge = $this->findOrCreateBadge($code, $catalogCode ?? $code, $artist);

        if (!$badge instanceof Badge) {
            return;
        }

        $userBadge = new UserBadge();
        $userBadge
            ->setUser($user)
            ->setBadge($badge)
            ->setContextData($contextData);

        $user->addUserBadge($userBadge);
        $this->entityManager->persist($userBadge);
    }

    private function findOrCreateBadge(string $code, string $catalogCode, ?Artist $artist): ?Badge
    {
        if (isset($this->badgesByCode[$code])) {
            return $this->badgesByCode[$code];
        }

        $badge = $this->entityManager->getRepository(Badge::class)->findOneBy(['code' => $code]);
        if ($badge instanceof Badge) {
            $this->badgesByCode[$code] = $badge;

            return $badge;
        }

        $definition = $this->badgeCatalog->get($catalogCode);
        if ($definition === null) {
            return null;
        }

        $name = $definition['name'];
        if ($artist instanceof Artist && $artist->getName() !== null) {
            $name = sprintf('%s: %s', $definition['name'], $artist->getName());
        }

        $badge = new Badge();
        $badge
            ->setCode($code)
            ->setName($name)
            ->setDescription($definition['description'])
            ->setScope($definition['scope'])
            ->setRuleType($definition['ruleType'])
            ->setRuleConfig($definition['ruleConfig'])
            ->setArtist($artist);

        $this->entityManager->persist($badge);
        $this->badgesByCode[$code] = $badge;

        return $badge;
    }

    private function artistScopedCode(string $baseCode, ?Artist $artist): string
    {
        $suffix = $artist?->getSlug() ?? 'artist';

        return sprintf('%s_%s', $baseCode, substr($suffix, 0, 58));
    }
}
