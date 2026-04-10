<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LeaderboardController extends AbstractController
{
    #[Route('/app/leaderboard', name: 'app_front_leaderboard')]
    public function index(UserRepository $userRepository): Response
    {
        $rankings = $userRepository->findTopByGlobalXp(50);
        $podium = array_slice($rankings, 0, 3);
        $others = array_slice($rankings, 3);

        $currentUser = $this->getUser();

        return $this->render('front/leaderboard/leaderboard.html.twig', [
            'podium' => $podium,
            'others' => $others,
            'contextName' => 'Global Hall',
            'currentUser' => $currentUser,
            'currentUserRank' => $currentUser instanceof User ? $userRepository->getGlobalRankPosition($currentUser) : null,
            'playerCount' => $userRepository->countActiveUsers(),
        ]);
    }
}
