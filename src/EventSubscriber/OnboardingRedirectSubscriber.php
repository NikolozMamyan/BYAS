<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\PublicPassportProfileService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OnboardingRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly PublicPassportProfileService $publicPassportProfileService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/app') || str_starts_with($path, '/app/onboarding')) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User || !$this->publicPassportProfileService->requiresOnboarding($user)) {
            return;
        }

        $next = $request->getRequestUri();
        $safeNext = str_starts_with($next, '/') && !str_starts_with($next, '//')
            ? $next
            : '/app/passport';

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_onboarding', [
            'next' => $safeNext,
        ])));
    }
}
