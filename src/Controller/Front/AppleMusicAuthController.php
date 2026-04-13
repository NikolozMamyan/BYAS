<?php

namespace App\Controller\Front;

use App\Entity\StreamingAccount;
use App\Entity\User;
use App\Service\AppleMusicService;
use App\Service\NotificationCenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/connect/apple-music', name: 'app_apple_music_')]
class AppleMusicAuthController extends AbstractController
{
    #[Route('', name: 'start', methods: ['GET'])]
    public function start(AppleMusicService $appleMusicService): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in to connect Apple Music.');
        }

        return $this->render('front/apple_music/connect.html.twig', [
            'developerToken' => $appleMusicService->getDeveloperToken(),
            'appName' => $appleMusicService->getAppName(),
            'linkUrl' => $this->generateUrl('app_apple_music_link'),
            'csrfToken' => $this->container->get('security.csrf.token_manager')->getToken('apple_music_link'),
        ]);
    }

    #[Route('/link', name: 'link', methods: ['POST'])]
    public function link(
        Request $request,
        AppleMusicService $appleMusicService,
        NotificationCenter $notificationCenter,
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid payload'], 400);
        }

        if (!$this->isCsrfTokenValid('apple_music_link', (string) ($payload['_token'] ?? ''))) {
            return $this->json(['message' => 'Invalid CSRF token'], 403);
        }

        try {
            $account = $appleMusicService->connect(
                $user,
                (string) ($payload['musicUserToken'] ?? ''),
                isset($payload['storefrontId']) ? (string) $payload['storefrontId'] : null,
            );

            $notificationCenter->notifyProviderConnected($user, StreamingAccount::PROVIDER_APPLE_MUSIC, $account->getDisplayName() ?? 'Apple Music');
            $notificationCenter->flush();

            return $this->json([
                'message' => 'Apple Music connected.',
                'redirectTo' => $this->generateUrl('app_front_passport_settings'),
            ]);
        } catch (\Throwable $exception) {
            return $this->json([
                'message' => $exception->getMessage(),
            ], 400);
        }
    }

    #[Route('/disconnect', name: 'disconnect', methods: ['POST'])]
    public function disconnect(AppleMusicService $appleMusicService): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $appleMusicService->disconnect($user);
        $this->addFlash('success', 'Apple Music account disconnected.');

        return $this->redirectToRoute('app_front_passport_settings');
    }
}
