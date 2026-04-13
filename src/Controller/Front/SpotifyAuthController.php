<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\NotificationCenter;
use App\Service\SpotifyOAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/connect/spotify', name: 'app_spotify_')]
class SpotifyAuthController extends AbstractController
{
    #[Route('', name: 'start', methods: ['GET'])]
    public function start(SpotifyOAuthService $spotifyOAuthService): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in to connect Spotify.');
        }

        return new RedirectResponse($spotifyOAuthService->buildAuthorizationUrl());
    }

    #[Route('/callback', name: 'callback', methods: ['GET'])]
    public function callback(
        Request $request,
        SpotifyOAuthService $spotifyOAuthService,
        NotificationCenter $notificationCenter,
    ): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in to connect Spotify.');
        }

        $error = (string) $request->query->get('error', '');
        if ($error !== '') {
            $this->addFlash('error', sprintf('Spotify authorization failed: %s', $error));

            return $this->redirectToRoute('app_front_passport');
        }

        $code = (string) $request->query->get('code', '');
        $state = $request->query->get('state');

        if ($code === '') {
            $this->addFlash('error', 'Missing Spotify authorization code.');

            return $this->redirectToRoute('app_front_passport');
        }

        try {
            $account = $spotifyOAuthService->handleCallback($user, $code, is_string($state) ? $state : null);
            $notificationCenter->notifyProviderConnected(
                $user,
                'spotify',
                $account->getDisplayName() ?? $account->getProviderUserId()
            );
            $notificationCenter->flush();

            $this->addFlash(
                'success',
                sprintf(
                    'Spotify account connected: %s',
                    $account->getDisplayName() ?? $account->getProviderUserId()
                )
            );
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_front_passport');
    }

    #[Route('/disconnect', name: 'disconnect', methods: ['POST'])]
    public function disconnect(SpotifyOAuthService $spotifyOAuthService): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $spotifyOAuthService->disconnect($user);
        $this->addFlash('success', 'Spotify account disconnected.');

        return $this->redirectToRoute('app_front_passport');
    }
}
