<?php

namespace App\Controller\Front;

use App\Entity\UserProfile;
use App\Repository\UserRepository;
use App\Service\XpEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicPassportController extends AbstractController
{
    #[Route('/p/{shareSlug}', name: 'app_public_passport', methods: ['GET'])]
    public function show(
        string $shareSlug,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        XpEngine $xpEngine,
    ): Response {
        $profile = $entityManager->getRepository(UserProfile::class)->findOneBy(['shareSlug' => $shareSlug]);

        if (!$profile instanceof UserProfile || !$profile->getUser()) {
            throw $this->createNotFoundException('Passport not found.');
        }

        $user = $profile->getUser();
        $fandoms = $user->getUserFandoms()->toArray();
        usort($fandoms, static fn ($a, $b): int => $b->getXp() <=> $a->getXp());

        $userBadges = $user->getUserBadges()->toArray();
        usort(
            $userBadges,
            static fn ($a, $b): int => $b->getAwardedAt()->getTimestamp() <=> $a->getAwardedAt()->getTimestamp()
        );

        return $this->render('front/passport/public_show.html.twig', [
            'user' => $user,
            'profile' => $profile,
            'fandoms' => array_slice($fandoms, 0, 6),
            'userBadges' => array_slice($userBadges, 0, 6),
            'globalRank' => $profile->isShowGlobalRank() ? $userRepository->getGlobalRankPosition($user) : null,
            'globalProgress' => $xpEngine->progressForXp($user->getGlobalXp()),
            'isPrivate' => !$profile->isProfilePublic(),
        ]);
    }
}
