<?php

namespace App\Controller\Front;

use App\Entity\UserProfile;
use App\Repository\UserRepository;
use App\Service\PublicPassportAnalyticsService;
use App\Service\XpEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicPassportController extends AbstractController
{
    #[Route('/p/{shareSlug}', name: 'app_public_passport', methods: ['GET'])]
    public function show(
        string $shareSlug,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        PublicPassportAnalyticsService $analytics,
        XpEngine $xpEngine,
    ): Response {
        $profile = $entityManager->getRepository(UserProfile::class)->findOneBy(['shareSlug' => $shareSlug]);

        if (!$profile instanceof UserProfile || !$profile->getUser()) {
            throw $this->createNotFoundException('Passport not found.');
        }

        $user = $profile->getUser();
        $viewer = $this->getUser();
        $analytics->recordVisit($profile, $request, $viewer instanceof \App\Entity\User ? $viewer : null);

        $fandoms = $user->getUserFandoms()->toArray();
        usort($fandoms, static fn ($a, $b): int => $b->getXp() <=> $a->getXp());

        $userBadges = $user->getUserBadges()->toArray();
        usort(
            $userBadges,
            static fn ($a, $b): int => $b->getAwardedAt()->getTimestamp() <=> $a->getAwardedAt()->getTimestamp()
        );

        $collectionItems = array_values(array_filter(
            $user->getCollectionItems()->toArray(),
            static fn ($item): bool => $item->isPublic()
        ));

        return $this->render('front/passport/public_show.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'fandoms' => $profile->isProfilePublic() && $profile->isShowFandomLevels() ? array_slice($fandoms, 0, 6) : [],
            'userBadges' => $profile->isProfilePublic() && $profile->isShowBadges() ? array_slice($userBadges, 0, 6) : [],
            'collectionItems' => $profile->isProfilePublic() && $profile->isShowCollection() ? array_slice($collectionItems, 0, 6) : [],
            'globalRank' => $profile->isShowGlobalRank() ? $userRepository->getGlobalRankPosition($user) : null,
            'globalProgress' => $xpEngine->progressForXp($user->getGlobalXp()),
            'isPrivate' => !$profile->isProfilePublic(),
            'isOwner' => $viewer === $user,
        ]);
    }

    #[Route('/p/{shareSlug}/message', name: 'app_public_passport_message', methods: ['GET'])]
    public function messageIntent(
        string $shareSlug,
        EntityManagerInterface $entityManager,
        PublicPassportAnalyticsService $analytics,
    ): RedirectResponse {
        $profile = $entityManager->getRepository(UserProfile::class)->findOneBy(['shareSlug' => $shareSlug]);

        if (!$profile instanceof UserProfile || !$profile->getUser()) {
            throw $this->createNotFoundException('Passport not found.');
        }

        $viewer = $this->getUser();

        if (!$viewer instanceof \App\Entity\User) {
            return $this->redirectToRoute('show_register', [
                'intent' => 'message',
                'passport' => $profile->getShareSlug(),
                'next' => $this->generateUrl('app_public_passport_message', ['shareSlug' => $profile->getShareSlug()]),
            ]);
        }

        $analytics->recordContactIntent($profile, $viewer);
        $this->addFlash('success', sprintf('Message intent saved for %s. Messaging will unlock in the social layer.', $profile->getUsername() ?? $profile->getUser()->getDisplayName()));

        return $this->redirectToRoute('app_front_passport');
    }
}
