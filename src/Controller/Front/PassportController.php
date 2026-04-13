<?php

namespace App\Controller\Front;

use App\Entity\StreamingAccount;
use App\Entity\User;
use App\Repository\PublicPassportContactIntentRepository;
use App\Repository\PublicPassportVisitRepository;
use App\Repository\UserRepository;
use App\Repository\XpTransactionRepository;
use App\Service\PublicPassportProfileService;
use App\Service\XpEngine;
use Doctrine\ORM\EntityManagerInterface;
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
    ): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $profile = $publicPassportProfileService->ensureProfile($user);

        $streamingAccounts = $user->getStreamingAccounts();

        $spotifyAccount = null;
        $isSpotifyConnected = false;
        $youtubeAccount = null;
        $isYoutubeConnected = false;
        $connectedSyncProviders = [];

        foreach ($streamingAccounts as $streamingAccount) {
            if ($streamingAccount->getProvider() === StreamingAccount::PROVIDER_SPOTIFY) {
                $spotifyAccount = $streamingAccount;
                $isSpotifyConnected = $streamingAccount->isConnected();
            }

            if ($streamingAccount->getProvider() === StreamingAccount::PROVIDER_YOUTUBE) {
                $youtubeAccount = $streamingAccount;
                $isYoutubeConnected = $streamingAccount->isConnected();
            }

            if ($streamingAccount->isConnected()) {
                $connectedSyncProviders[] = $streamingAccount->getProvider();
            }
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
            'oauthAccounts' => $user->getOauthAccounts(),
            'streamingAccounts' => $streamingAccounts,
            'spotifyAccount' => $spotifyAccount,
            'isSpotifyConnected' => $isSpotifyConnected,
            'youtubeAccount' => $youtubeAccount,
            'isYoutubeConnected' => $isYoutubeConnected,
            'connectedSyncProviders' => array_values(array_unique($connectedSyncProviders)),
            'xpTransactions' => $xpTransactionRepository->findRecentForUser($user, 8),
            'items' => $user->getCollectionItems(),
            'userBadges' => $userBadges,
            'globalRank' => $userRepository->getGlobalRankPosition($user),
            'globalProgress' => $xpEngine->progressForXp($user->getGlobalXp()),
            'publicVisitCount' => $visitRepository->countForProfile($profile),
            'publicVisitCount7d' => $visitRepository->countForProfileSince($profile, new \DateTimeImmutable('-7 days')),
            'recentPublicVisits' => $visitRepository->findRecentForProfile($profile, 5),
            'contactIntentCount' => $contactIntentRepository->countForProfile($profile),
        ]);
    }

    #[Route('/passport/share', name: 'passport_share', methods: ['GET'])]
    public function share(
        UserRepository $userRepository,
        XpEngine $xpEngine,
        PublicPassportProfileService $publicPassportProfileService,
    ): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
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
            'userBadges' => array_slice($userBadges, 0, 3),
            'globalRank' => $userRepository->getGlobalRankPosition($user),
            'globalProgress' => $xpEngine->progressForXp($user->getGlobalXp()),
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
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $profile = $publicPassportProfileService->ensureProfile($user);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('passport_settings', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $profile
                ->setIsProfilePublic($request->request->has('isProfilePublic'))
                ->setShowGlobalRank($request->request->has('showGlobalRank'))
                ->setShowFandomLevels($request->request->has('showFandomLevels'))
                ->setShowBadges($request->request->has('showBadges'))
                ->setShowCollection($request->request->has('showCollection'))
                ->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->flush();
            $this->addFlash('success', 'Passport privacy settings updated.');

            return $this->redirectToRoute('app_front_passport_settings');
        }

        return $this->render('front/passport/settings.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'publicUrl' => $this->generateUrl('app_public_passport', [
                'shareSlug' => $profile->getShareSlug(),
            ], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }
}
