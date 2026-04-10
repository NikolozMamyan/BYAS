<?php

namespace App\Controller\Api\Sync;

use App\Entity\User;
use App\Service\StreamingSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/streaming', name: 'api_streaming_')]
class StreamingSyncController extends AbstractController
{
    #[Route('/sync', name: 'sync', methods: ['POST'])]
    public function sync(
        #[CurrentUser] ?User $user,
        StreamingSyncService $syncService
    ): JsonResponse {
        if (!$user instanceof User) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $result = $syncService->syncUser($user);
        } catch (\Throwable $exception) {
            return $this->json([
                'message' => 'Sync failed',
                'error' => $exception->getMessage(),
            ], 500);
        }

        return $this->json([
            'message' => 'Sync completed',
            'result' => $result,
        ]);
    }
}
