<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\UserActivityService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class UserActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UserActivityService $activityService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['recordAppOpen', -10],
        ];
    }

    public function recordAppOpen(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('GET') || !str_starts_with($request->getPathInfo(), '/app/')) {
            return;
        }

        $user = $this->security->getUser();
        if ($user instanceof User) {
            $this->activityService->recordAppOpen($user);
        }
    }
}
