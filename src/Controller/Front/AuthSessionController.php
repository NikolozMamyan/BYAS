<?php

namespace App\Controller\Front;

use App\Service\AuthCookieService;
use App\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app', name: 'app_front_')]
final class AuthSessionController extends AbstractController
{
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(
        Request $request,
        SessionManager $sessionManager,
        AuthCookieService $authCookieService
    ): RedirectResponse {
        $plainToken = $request->cookies->get(AuthCookieService::AUTH_COOKIE_NAME);

        $session = is_string($plainToken) && trim($plainToken) !== ''
            ? $sessionManager->findActiveSessionByPlainToken($plainToken)
            : null;

        if ($session) {
            $sessionManager->revoke($session, 'logout');
        }

        $response = $this->redirect('/');
        $authCookieService->clearAuthenticationCookies($response);

        return $response;
    }
}
