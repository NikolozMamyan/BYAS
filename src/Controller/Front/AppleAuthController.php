<?php

namespace App\Controller\Front;

use App\Service\AppleOAuthService;
use App\Service\AuthCookieService;
use App\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth/apple', name: 'app_apple_')]
class AppleAuthController extends AbstractController
{
    #[Route('/start', name: 'start', methods: ['GET'])]
    public function start(Request $request, AppleOAuthService $appleOAuthService): Response
    {
        $next = $request->query->get('next');

        return new RedirectResponse(
            $appleOAuthService->buildAuthorizationUrl(is_string($next) ? $next : null)
        );
    }

    #[Route('/callback', name: 'callback', methods: ['POST'])]
    public function callback(
        Request $request,
        AppleOAuthService $appleOAuthService,
        SessionManager $sessionManager,
        AuthCookieService $authCookieService,
    ): Response {
        $error = (string) $request->request->get('error', '');

        if ($error !== '') {
            $this->addFlash('error', sprintf('Apple authorization failed: %s', $error));

            return $this->redirectToRoute('show_login');
        }

        $code = (string) $request->request->get('code', '');
        $state = $request->request->get('state');
        $userPayloadRaw = $request->request->get('user');
        $userPayload = is_string($userPayloadRaw) ? json_decode($userPayloadRaw, true) : null;

        if ($code === '') {
            $this->addFlash('error', 'Missing Apple authorization code.');

            return $this->redirectToRoute('show_login');
        }

        try {
            $result = $appleOAuthService->handleCallback(
                $code,
                is_string($state) ? $state : null,
                is_array($userPayload) ? $userPayload : null,
            );

            $user = $result['user'];
            [$session, $plainToken, $deviceId] = $sessionManager->createSession($user, 'Apple OAuth');

            $next = $result['next'] ?? '/app/passport';
            $next = is_string($next) && str_starts_with($next, '/') && !str_starts_with($next, '//')
                ? $next
                : '/app/passport';

            $response = new RedirectResponse($next);
            $authCookieService->attachAuthenticationCookies($response, $request, $plainToken, $deviceId, $session->getExpiresAt());

            return $response;
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('show_login');
        }
    }
}
