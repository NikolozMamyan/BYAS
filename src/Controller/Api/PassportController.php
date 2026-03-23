<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\UserFandom;
use App\Entity\UserBadge;
use App\Entity\CollectionItem;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/passport', name: 'app_api_passport_')]
class PassportController extends AbstractController
{
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User) {
            return $this->json([
                'message' => 'User not authenticated.',
            ], 401);
        }

        $fandoms = [];
        foreach ($user->getUserFandoms() as $userFandom) {
            if (!$userFandom instanceof UserFandom) {
                continue;
            }

            $artist = $userFandom->getArtist();

            $fandoms[] = [
                'artist' => $artist ? [
                    'id' => $artist->getId(),
                    'name' => $artist->getName(),
                    'slug' => $artist->getSlug(),
                    'type' => $artist->getType(),
                    'coverImageUrl' => $artist->getCoverImageUrl(),
                ] : null,
                'xp' => $userFandom->getXp(),
                'level' => $userFandom->getLevel(),
                'rankPosition' => $userFandom->getRankPosition(),
                'rankPercentile' => $userFandom->getRankPercentile(),
                'progressPercent' => $userFandom->getProgressPercent(),
                'firstEngagedAt' => $userFandom->getFirstEngagedAt()?->format(\DateTimeInterface::ATOM),
                'lastXpAt' => $userFandom->getLastXpAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        $badges = [];
        if ($user->getProfile()?->isShowBadges() ?? true) {
            foreach ($user->getUserBadges() ?? [] as $userBadge) {
                if (!$userBadge instanceof UserBadge) {
                    continue;
                }

                $badge = $userBadge->getBadge();

                $badges[] = [
                    'code' => $badge?->getCode(),
                    'name' => $badge?->getName(),
                    'description' => $badge?->getDescription(),
                    'iconUrl' => $badge?->getIconUrl(),
                    'awardedAt' => $userBadge->getAwardedAt()->format(\DateTimeInterface::ATOM),
                ];
            }
        }

        $collection = [];
        if ($user->getProfile()?->isShowCollection() ?? true) {
            foreach ($user->getCollectionItems() ?? [] as $item) {
                if (!$item instanceof CollectionItem) {
                    continue;
                }

                $collection[] = [
                    'id' => $item->getId(),
                    'title' => $item->getTitle(),
                    'description' => $item->getDescription(),
                    'quantity' => $item->getQuantity(),
                    'isPublic' => $item->isPublic(),
                    'declaredAt' => $item->getDeclaredAt()->format(\DateTimeInterface::ATOM),
                    'type' => $item->getType()?->getLabel(),
                    'artist' => $item->getArtist()?->getName(),
                    'metadata' => $item->getMetadata(),
                ];
            }
        }

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'displayName' => $user->getDisplayName(),
                'avatarUrl' => $user->getAvatarUrl(),
                'globalXp' => $user->getGlobalXp(),
                'globalLevel' => $user->getGlobalLevel(),
            ],
            'profile' => $user->getProfile() ? [
                'username' => $user->getProfile()->getUsername(),
                'bio' => $user->getProfile()->getBio(),
                'countryCode' => $user->getProfile()->getCountryCode(),
                'shareSlug' => $user->getProfile()->getShareSlug(),
            ] : null,
            'fandoms' => $fandoms,
            'badges' => $badges,
            'collection' => $collection,
        ]);
    }
}