<?php

namespace App\Controller\Front;

use App\Entity\StreamingAccount;
use App\Entity\User;
use App\Repository\PublicPassportContactIntentRepository;
use App\Repository\PublicPassportVisitRepository;
use App\Repository\UserRepository;
use App\Repository\XpTransactionRepository;
use App\Service\AppleMusicService;
use App\Service\AvatarManager;
use App\Service\LevelBadgeCatalog;
use App\Service\NotificationCenter;
use App\Service\PublicPassportProfileService;
use App\Service\XpEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/app', name: 'app_front_')]
class PassportController extends AbstractController
{
    #[Route('/passport', name: 'passport', methods: ['GET'])]
    public function show(
        UserRepository $userRepository,
        XpEngine $xpEngine,
        XpTransactionRepository $xpTransactionRepository,
        PublicPassportProfileService $publicPassportProfileService,
        PublicPassportVisitRepository $visitRepository,
        PublicPassportContactIntentRepository $contactIntentRepository,
        AppleMusicService $appleMusicService,
        LevelBadgeCatalog $levelBadgeCatalog,
        NotificationCenter $notificationCenter,
    ): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        if ($publicPassportProfileService->requiresOnboarding($user)) {
            return $this->redirectToRoute('app_onboarding', [
                'next' => $this->generateUrl('app_front_passport'),
            ]);
        }

        $profile = $publicPassportProfileService->ensureProfile($user);

        $streamingAccounts = $user->getStreamingAccounts();

        $spotifyAccount = null;
        $isSpotifyConnected = false;
        $appleMusicAccount = null;
        $isAppleMusicConnected = false;
        $youtubeAccount = null;
        $isYoutubeConnected = false;
        $connectedSyncProviders = [];

        foreach ($streamingAccounts as $streamingAccount) {
            if ($streamingAccount->getProvider() === StreamingAccount::PROVIDER_SPOTIFY) {
                $spotifyAccount = $streamingAccount;
                $isSpotifyConnected = $streamingAccount->isConnected();
            }

            if ($streamingAccount->getProvider() === StreamingAccount::PROVIDER_APPLE_MUSIC) {
                $appleMusicAccount = $streamingAccount;
                $isAppleMusicConnected = $streamingAccount->isConnected();
            }

            if ($streamingAccount->getProvider() === StreamingAccount::PROVIDER_YOUTUBE) {
                $youtubeAccount = $streamingAccount;
                $isYoutubeConnected = $streamingAccount->isConnected();
            }

            if (
                $streamingAccount->isConnected()
                && (
                    $streamingAccount->getProvider() !== StreamingAccount::PROVIDER_APPLE_MUSIC
                    || $appleMusicService->isConfigured()
                )
            ) {
                $connectedSyncProviders[] = $streamingAccount->getProvider();
            }
        }

        if ($connectedSyncProviders === []) {
            $notificationCenter->ensureStreamingSetupReminder($user);
        }

        $fandoms = $user->getUserFandoms()->toArray();
        usort($fandoms, static fn ($a, $b): int => $b->getXp() <=> $a->getXp());

        $userBadges = $user->getUserBadges()->toArray();
        usort(
            $userBadges,
            static fn ($a, $b): int => $b->getAwardedAt()->getTimestamp() <=> $a->getAwardedAt()->getTimestamp()
        );

        return $this->render('front/passport/show.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'fandoms' => $fandoms,
            'topFandoms' => array_slice($fandoms, 0, 3),
            'oauthAccounts' => $user->getOauthAccounts(),
            'streamingAccounts' => $streamingAccounts,
            'spotifyAccount' => $spotifyAccount,
            'isSpotifyConnected' => $isSpotifyConnected,
            'appleMusicAccount' => $appleMusicAccount,
            'isAppleMusicConnected' => $isAppleMusicConnected,
            'youtubeAccount' => $youtubeAccount,
            'isYoutubeConnected' => $isYoutubeConnected,
            'connectedSyncProviders' => array_values(array_unique($connectedSyncProviders)),
            'xpTransactions' => $xpTransactionRepository->findRecentForUser($user, 8),
            'items' => $user->getCollectionItems(),
            'collectionHighlights' => $this->buildCollectionHighlights($user->getCollectionItems()->toArray()),
            'userBadges' => $userBadges,
            'featuredBadges' => array_slice($userBadges, 0, 5),
            'globalRank' => $userRepository->getGlobalRankPosition($user),
            'globalProgress' => $xpEngine->progressForXp($user->getGlobalXp()),
            'globalLevelBadge' => $levelBadgeCatalog->forLevel($user->getGlobalLevel()),
            'fandomLevelBadges' => $this->buildFandomLevelBadges($fandoms, $levelBadgeCatalog),
            'publicVisitCount' => $visitRepository->countForProfile($profile),
            'publicVisitCount7d' => $visitRepository->countForProfileSince($profile, new \DateTimeImmutable('-7 days')),
            'recentPublicVisits' => $visitRepository->findRecentForProfile($profile, 5),
            'contactIntentCount' => $contactIntentRepository->countForProfile($profile),
            'publicPassportUrl' => $this->generateUrl('app_public_passport', [
                'shareSlug' => $profile->getShareSlug(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/passport/share', name: 'passport_share', methods: ['GET'])]
    public function share(
        UserRepository $userRepository,
        XpEngine $xpEngine,
        PublicPassportProfileService $publicPassportProfileService,
        LevelBadgeCatalog $levelBadgeCatalog,
    ): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        if ($publicPassportProfileService->requiresOnboarding($user)) {
            return $this->redirectToRoute('app_onboarding', [
                'next' => $this->generateUrl('app_front_passport_share'),
            ]);
        }

        $profile = $publicPassportProfileService->ensureProfile($user);
        $fandoms = $user->getUserFandoms()->toArray();
        usort($fandoms, static fn ($a, $b): int => $b->getXp() <=> $a->getXp());

        $userBadges = $user->getUserBadges()->toArray();
        usort(
            $userBadges,
            static fn ($a, $b): int => $b->getAwardedAt()->getTimestamp() <=> $a->getAwardedAt()->getTimestamp()
        );

        return $this->render('front/passport/share.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'topFandom' => $fandoms[0] ?? null,
            'topFandomLevelBadge' => isset($fandoms[0]) ? $levelBadgeCatalog->forLevel($fandoms[0]->getLevel()) : null,
            'userBadges' => array_slice($userBadges, 0, 3),
            'globalRank' => $userRepository->getGlobalRankPosition($user),
            'globalProgress' => $xpEngine->progressForXp($user->getGlobalXp()),
            'globalLevelBadge' => $levelBadgeCatalog->forLevel($user->getGlobalLevel()),
            'shareUrl' => $this->generateUrl('app_public_passport', [
                'shareSlug' => $profile->getShareSlug(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/passport/settings', name: 'passport_settings', methods: ['GET', 'POST'])]
    public function settings(
        Request $request,
        PublicPassportProfileService $publicPassportProfileService,
        EntityManagerInterface $entityManager,
        AvatarManager $avatarManager,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        if ($publicPassportProfileService->requiresOnboarding($user)) {
            return $this->redirectToRoute('app_onboarding', [
                'next' => $this->generateUrl('app_front_passport_settings'),
            ]);
        }

        $profile = $publicPassportProfileService->ensureProfile($user);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('passport_settings', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            try {
                $action = (string) $request->request->get('action', 'save_settings');

                if ($action === 'remove_avatar') {
                    $avatarManager->deleteCurrentAvatarFile($user);
                    $user->setAvatarUrl(null);
                    $entityManager->flush();
                    $this->addFlash('success', 'Avatar removed.');

                    return $this->redirectToRoute('app_front_passport_settings');
                }

                $firstName = trim((string) $request->request->get('firstName', ''));
                $lastName = trim((string) $request->request->get('lastName', ''));
                $displayName = trim((string) $request->request->get('displayName', ''));

                if ($displayName === '') {
                    $displayName = trim($firstName . ' ' . $lastName);
                }

                if ($displayName === '') {
                    $displayName = $user->getDisplayName() ?? 'Fan';
                }

                $user
                    ->setFirstName($firstName !== '' ? $firstName : null)
                    ->setLastName($lastName !== '' ? $lastName : null)
                    ->setDisplayName($displayName);

                $avatarFile = $request->files->get('avatar');

                if ($avatarFile instanceof UploadedFile && $avatarFile->getError() === UPLOAD_ERR_OK) {
                    $user->setAvatarUrl($avatarManager->upload($user, $avatarFile));
                }

                $profile
                    ->setIsProfilePublic($request->request->has('isProfilePublic'))
                    ->setShowGlobalRank($request->request->has('showGlobalRank'))
                    ->setShowFandomLevels($request->request->has('showFandomLevels'))
                    ->setShowBadges($request->request->has('showBadges'))
                    ->setShowCollection($request->request->has('showCollection'))
                    ->setUpdatedAt(new \DateTimeImmutable());

                $entityManager->flush();
                $this->addFlash('success', 'Account and Passport settings updated.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_front_passport_settings');
        }

        return $this->render('front/passport/settings.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'oauthAccounts' => $user->getOauthAccounts(),
            'spotifyAccount' => $user->getStreamingAccountByProvider(StreamingAccount::PROVIDER_SPOTIFY),
            'appleMusicAccount' => $user->getStreamingAccountByProvider(StreamingAccount::PROVIDER_APPLE_MUSIC),
            'youtubeAccount' => $user->getStreamingAccountByProvider(StreamingAccount::PROVIDER_YOUTUBE),
            'publicUrl' => $this->generateUrl('app_public_passport', [
                'shareSlug' => $profile->getShareSlug(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    /**
     * @param array<int, mixed> $fandoms
     *
     * @return array<int, array{level:int,mainTitle:string,subLevelTitle:?string,fullTitle:string}>
     */
    private function buildFandomLevelBadges(array $fandoms, LevelBadgeCatalog $levelBadgeCatalog): array
    {
        $badges = [];

        foreach ($fandoms as $fandom) {
            if (!$fandom instanceof \App\Entity\UserFandom || $fandom->getId() === null) {
                continue;
            }

            $badges[$fandom->getId()] = $levelBadgeCatalog->forLevel($fandom->getLevel());
        }

        return $badges;
    }

    /**
     * @param array<int, mixed> $items
     *
     * @return array<int, array{label:string,count:int,icon:string}>
     */
    private function buildCollectionHighlights(array $items): array
    {
        $groups = [];

        foreach ($items as $item) {
            if (!$item instanceof \App\Entity\CollectionItem) {
                continue;
            }

            $label = trim((string) $item->getType()?->getLabel());
            $key = $label !== '' ? mb_strtolower($label) : 'collection';

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'label' => $label !== '' ? $label : 'Collection',
                    'count' => 0,
                    'icon' => $this->iconForCollectionType($label),
                ];
            }

            $groups[$key]['count'] += max(1, $item->getQuantity());
        }

        uasort($groups, static fn (array $left, array $right): int => $right['count'] <=> $left['count']);

        return array_slice(array_values($groups), 0, 4);
    }

    private function iconForCollectionType(?string $label): string
    {
        $normalized = mb_strtolower(trim((string) $label));

        return match (true) {
            str_contains($normalized, 'light') => 'fa-lightbulb',
            str_contains($normalized, 'photo'), str_contains($normalized, 'card') => 'fa-id-badge',
            str_contains($normalized, 'vinyl'), str_contains($normalized, 'album'), str_contains($normalized, 'cd') => 'fa-compact-disc',
            default => 'fa-record-vinyl',
        };
    }
}
