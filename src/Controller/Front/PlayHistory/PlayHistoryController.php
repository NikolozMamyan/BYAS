<?php

namespace App\Controller\Front\PlayHistory;

use App\Entity\User;
use App\Repository\StreamingPlayHistoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app', name: 'app_front_')]
class PlayHistoryController extends AbstractController
{
    #[Route('/play/history', name: 'play_history', methods: ['GET'])]
    public function index(StreamingPlayHistoryRepository $streamingPlayHistoryRepository): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $playHistory = $streamingPlayHistoryRepository->findBy(
            ['user' => $user],
            ['playedAt' => 'DESC']
        );
        return $this->render('front/play_history/index.html.twig', [
            'title' => 'Play History',
            'playHistory' => $playHistory,
            'user' => $user,
        ]);
    }
}