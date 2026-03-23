<?php

namespace App\Controller\Front;

use App\Repository\UserFandomRepository; // Supposons que c'est ta table pivot
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LeaderboardController extends AbstractController
{
    #[Route('/app/leaderboard', name: 'app_front_leaderboard')]
    public function index(
        Request $request, 
        UserRepository $userRepo, 
        // UserFandomRepository $fandomRepo
    ): Response {
     $rankings = [
            ['displayName' => 'BangtanKing', 'globalXp' => 12540, 'rank' => 'S+'],
            ['displayName' => 'Lisa_Fan', 'globalXp' => 11200, 'rank' => 'S'],
            ['displayName' => 'Hanni_Bunny', 'globalXp' => 9850, 'rank' => 'S'],
            ['displayName' => 'StrayKids_Stay', 'globalXp' => 8400, 'rank' => 'A'],
            ['displayName' => 'Blink_Paris', 'globalXp' => 7900, 'rank' => 'A'],
            ['displayName' => 'KpopLover99', 'globalXp' => 7200, 'rank' => 'B'],
            ['displayName' => 'MangaOtaku', 'globalXp' => 6800, 'rank' => 'B'],
            ['displayName' => 'TwiceOnce', 'globalXp' => 5400, 'rank' => 'C'],
            ['displayName' => 'Aespa_Next', 'globalXp' => 4200, 'rank' => 'C'],
            ['displayName' => 'Ive_Dive', 'globalXp' => 3100, 'rank' => 'D'],
        ];

        // 2. Découpage pour le Podium (les 3 premiers)
        $podium = array_slice($rankings, 0, 3);

        // 3. Le reste des utilisateurs (à partir du 4ème)
        $others = array_slice($rankings, 3);

        return $this->render('front/leaderboard/leaderboard.html.twig', [
            'podium' => $podium,
            'others' => $others,
            'contextName' => 'Global Hall',
            'currentUser' => $this->getUser()
        ]);
    }
}