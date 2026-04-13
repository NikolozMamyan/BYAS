<?php

namespace App\Controller\Front;

use App\Entity\AppNotification;
use App\Entity\User;
use App\Repository\AppNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/notifications')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'app_front_notifications', methods: ['GET'])]
    public function index(AppNotificationRepository $notificationRepository): Response
    {
        $user = $this->getAuthenticatedUser();
        $notifications = $notificationRepository->findRecentForUser($user, 30);

        return $this->render('front/notifications/index.html.twig', [
            'notifications' => $notifications,
            'notificationCount' => count($notifications),
            'unreadCount' => $notificationRepository->countUnreadForUser($user),
        ]);
    }

    #[Route('/panel', name: 'app_front_notifications_panel', methods: ['GET'])]
    public function panel(AppNotificationRepository $notificationRepository): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $notifications = $notificationRepository->findRecentForUser($user, 6);

        return $this->json([
            'html' => $this->renderView('front/notifications/_panel.html.twig', [
                'notifications' => $notifications,
                'unreadCount' => $notificationRepository->countUnreadForUser($user),
            ]),
            'unreadCount' => $notificationRepository->countUnreadForUser($user),
        ]);
    }

    #[Route('/{id}/open', name: 'app_front_notifications_open', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function open(AppNotification $notification, EntityManagerInterface $entityManager): RedirectResponse
    {
        $user = $this->getAuthenticatedUser();
        $this->denyAccessUnlessGrantedToNotification($notification, $user);

        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $entityManager->flush();
        }

        return $this->redirect($notification->getTargetUrl() ?? $this->generateUrl('app_front_notifications'));
    }

    #[Route('/mark-all-read', name: 'app_front_notifications_mark_all_read', methods: ['POST'])]
    public function markAllRead(Request $request, AppNotificationRepository $notificationRepository): RedirectResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$this->isCsrfTokenValid('mark_all_notifications_read', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $notificationRepository->markAllReadForUser($user);

        return $this->redirectToRoute('app_front_notifications');
    }

    #[Route('/{id}/mark-read', name: 'app_front_notifications_mark_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markRead(
        AppNotification $notification,
        Request $request,
        AppNotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();
        $this->denyAccessUnlessGrantedToNotification($notification, $user);

        if (!$this->isCsrfTokenValid('mark_notification_read_'.$notification->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $entityManager->flush();
        }

        return $this->json([
            'ok' => true,
            'unreadCount' => $notificationRepository->countUnreadForUser($user),
        ]);
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        return $user;
    }

    private function denyAccessUnlessGrantedToNotification(AppNotification $notification, User $user): void
    {
        if ($notification->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Notification not found.');
        }
    }
}
