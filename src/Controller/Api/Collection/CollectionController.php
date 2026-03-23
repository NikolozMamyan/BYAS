<?php

namespace App\Controller\Api\Collection;

use App\Entity\CollectionItem;
use App\Entity\User;
use App\Repository\CollectionItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/collection', name: 'app_api_collection_')]
class CollectionController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        #[CurrentUser] ?User $user,
        CollectionItemRepository $collectionItemRepository
    ): JsonResponse {
        if (!$user instanceof User) {
            return $this->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $items = $collectionItemRepository->findAllByUser($user);

        return $this->json([
            'count' => count($items),
            'items' => array_map(
                fn (CollectionItem $item): array => [
                    'id' => $item->getId(),
                    'title' => $item->getTitle(),
                    'description' => $item->getDescription(),
                    'quantity' => $item->getQuantity(),
                    'isPublic' => $item->isPublic(),
                    'declaredAt' => $item->getDeclaredAt()->format(\DateTimeInterface::ATOM),
                    'type' => $item->getType() ? [
                        'id' => $item->getType()->getId(),
                        'code' => $item->getType()->getCode(),
                        'label' => $item->getType()->getLabel(),
                    ] : null,
                    'artist' => $item->getArtist() ? [
                        'id' => $item->getArtist()->getId(),
                        'name' => $item->getArtist()->getName(),
                        'slug' => $item->getArtist()->getSlug(),
                    ] : null,
                    'metadata' => $item->getMetadata(),
                ],
                $items
            ),
        ]);
    }
}