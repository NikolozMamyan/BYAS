<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\AuthCookieService;
use App\Service\SessionManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class PersistentSessionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly AuthCookieService $authCookieService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($request->attributes->get('_clear_invalid_auth_cookie') === true) {
            $this->authCookieService->clearAuthenticationCookies($response);
        }

        $user = $request->attributes->get('_security_main_user');
        $plainToken = $request->attributes->get('_auth_plain_token');

        if (!$user instanceof User || !is_string($plainToken) || trim($plainToken) === '') {
            return;
        }

        $session = $this->sessionManager->findActiveSessionByPlainToken($plainToken);

        if ($session === null) {
            return;
        }

        $this->authCookieService->refreshAuthenticationCookie(
            $response,
            $request,
            $plainToken,
            $session->getExpiresAt(),
        );
    }
}
