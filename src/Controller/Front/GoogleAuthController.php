<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthCookieService;
use App\Service\GoogleOAuthService;
use App\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/google', name: 'app_google_')]
class GoogleAuthController extends AbstractController
{
    #[Route('/start', name: 'start', methods: ['GET'])]
    public function start(Request $request, GoogleOAuthService $googleOAuthService): Response
    {
        $next = $request->query->get('next');

        return new RedirectResponse(
            $googleOAuthService->buildAuthorizationUrl(
                GoogleOAuthService::PURPOSE_LOGIN,
                is_string($next) ? $next : null
            )
        );
    }

    #[Route('/callback', name: 'callback', methods: ['GET'])]
    public function callback(
        Request $request,
        GoogleOAuthService $googleOAuthService,
        SessionManager $sessionManager,
        AuthCookieService $authCookieService,
        UserRepository $userRepository,
    ): Response {
        $error = (string) $request->query->get('error', '');

        if ($error !== '') {
            $this->addFlash('error', sprintf('Google authorization failed: %s', $error));

            return $this->redirectToRoute('show_login');
        }

        $code = (string) $request->query->get('code', '');
        $state = $request->query->get('state');

        if ($code === '') {
            $this->addFlash('error', 'Missing Google authorization code.');

            return $this->redirectToRoute('show_login');
        }

        try {
            $result = $googleOAuthService->consumeCallback($code, is_string($state) ? $state : null);
            $payload = $result['payload'];

            return match ($payload['purpose'] ?? GoogleOAuthService::PURPOSE_LOGIN) {
                GoogleOAuthService::PURPOSE_CONNECT_YOUTUBE => $this->handleYoutubeConnect($googleOAuthService, $userRepository, $result),
                default => $this->handleLogin($request, $googleOAuthService, $sessionManager, $authCookieService, $result),
            };
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('show_login');
        }
    }

    private function handleLogin(
        Request $request,
        GoogleOAuthService $googleOAuthService,
        SessionManager $sessionManager,
        AuthCookieService $authCookieService,
        array $result
    ): Response {
        $user = $googleOAuthService->findOrCreateUserFromGoogle($result['profile'], $result['tokenData']);
        [$session, $plainToken, $deviceId] = $sessionManager->createSession($user, 'Google OAuth');

        $next = $result['payload']['next'] ?? '/app/passport';
        $next = is_string($next) && str_starts_with($next, '/') && !str_starts_with($next, '//')
            ? $next
            : '/app/passport';

        $response = new RedirectResponse($next);
        $authCookieService->attachAuthenticationCookies($response, $request, $plainToken, $deviceId, $session->getExpiresAt());

        return $response;
    }

    private function handleYoutubeConnect(
        GoogleOAuthService $googleOAuthService,
        UserRepository $userRepository,
        array $result
    ): Response {
        $userId = isset($result['payload']['userId']) ? (int) $result['payload']['userId'] : 0;
        $currentUser = $userId > 0 ? $userRepository->find($userId) : null;

        if (!$currentUser instanceof User) {
            $this->addFlash('error', 'You must be logged in to connect YouTube.');

            return $this->redirectToRoute('show_login', [
                'next' => '/app/passport',
            ]);
        }

        $account = $googleOAuthService->connectYoutubeForUser($currentUser, $result['profile'], $result['tokenData']);

        $this->addFlash(
            'success',
            sprintf('YouTube account connected: %s', $account->getDisplayName() ?? $account->getProviderUserId())
        );

        return $this->redirectToRoute('app_front_passport');
    }
}
