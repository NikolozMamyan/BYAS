<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\User;

#[Route('/api', name: 'app_api_')]
class AuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Cette méthode est volontairement vide :
        // le LoginAuthenticator intercepte la requête avant.
        throw new \LogicException('This route is handled by the LoginAuthenticator.');
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user instanceof User) {
            return $this->json([
                'message' => 'User not authenticated.',
            ], 401);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'avatarUrl' => $user->getAvatarUrl(),
            'globalXp' => $user->getGlobalXp(),
            'globalLevel' => $user->getGlobalLevel(),
            'roles' => $user->getRoles(),
            'isActive' => $user->isActive(),
            'profile' => $user->getProfile() ? [
                'username' => $user->getProfile()->getUsername(),
                'bio' => $user->getProfile()->getBio(),
                'countryCode' => $user->getProfile()->getCountryCode(),
                'isProfilePublic' => $user->getProfile()->isProfilePublic(),
                'showGlobalRank' => $user->getProfile()->isShowGlobalRank(),
                'showCollection' => $user->getProfile()->isShowCollection(),
                'showBadges' => $user->getProfile()->isShowBadges(),
                'showFandomLevels' => $user->getProfile()->isShowFandomLevels(),
                'shareSlug' => $user->getProfile()->getShareSlug(),
            ] : null,
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['POST', 'GET'])]
    public function logout(): void
    {
        throw new \LogicException('This route is handled by the firewall logout.');
    }
}