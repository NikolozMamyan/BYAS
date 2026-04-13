<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\GoogleOAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/connect/youtube', name: 'app_youtube_')]
class YouTubeAuthController extends AbstractController
{
    #[Route('', name: 'start', methods: ['GET'])]
    public function start(GoogleOAuthService $googleOAuthService): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in to connect YouTube.');
        }

        return new RedirectResponse(
            $googleOAuthService->buildAuthorizationUrl(
                GoogleOAuthService::PURPOSE_CONNECT_YOUTUBE,
                '/app/passport',
                $user->getId()
            )
        );
    }

    #[Route('/disconnect', name: 'disconnect', methods: ['POST'])]
    public function disconnect(GoogleOAuthService $googleOAuthService): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $googleOAuthService->disconnectYoutube($user);
        $this->addFlash('success', 'YouTube account disconnected.');

        return $this->redirectToRoute('app_front_passport');
    }
}
