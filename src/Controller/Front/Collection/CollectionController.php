<?php

namespace App\Controller\Front\Collection;

use App\Entity\User;
use App\Repository\CollectionItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app/collection', name: 'app_front_collection_')]
class CollectionController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(CollectionItemRepository $collectionItemRepository): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $items = $collectionItemRepository->findAllByUser($user);

        $shelves = [];
        foreach ($items as $item) {
            $label = $item->getType()?->getLabel() ?? 'Autres';

            if (!isset($shelves[$label])) {
                $shelves[$label] = [];
            }

            $shelves[$label][] = $item;
        }

        return $this->render('front/collection/index.html.twig', [
            'user' => $user,
            'shelves' => $shelves,
            'items' => $items,
        ]);
    }
}