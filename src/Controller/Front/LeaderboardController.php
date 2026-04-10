<?php

namespace App\Controller\Front;

use App\Entity\Artist;
use App\Entity\User;
use App\Entity\UserFandom;
use App\Repository\UserFandomRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LeaderboardController extends AbstractController
{
    #[Route('/app/leaderboard', name: 'app_front_leaderboard')]
    public function index(
        Request $request,
        UserRepository $userRepository,
        UserFandomRepository $userFandomRepository,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $artistSlug = $request->query->get('artist');
        $selectedArtist = is_string($artistSlug) && trim($artistSlug) !== ''
            ? $entityManager->getRepository(Artist::class)->findOneBy(['slug' => trim($artistSlug)])
            : null;

        if ($selectedArtist instanceof Artist) {
            $rankings = array_map([$this, 'normalizeFandomRanking'], $userFandomRepository->findTopByArtist($selectedArtist, 50));
            $playerCount = count($rankings);
            $contextName = $selectedArtist->getName() ?? 'Fandom';
            $mode = 'Fandom';
        } else {
            $rankings = array_map([$this, 'normalizeGlobalRanking'], $userRepository->findTopByGlobalXp(50));
            $playerCount = $userRepository->countActiveUsers();
            $contextName = 'Global Hall';
            $mode = 'Global';
        }

        $podium = array_slice($rankings, 0, 3);
        $others = array_slice($rankings, 3);

        $currentUser = $this->getUser();
        $currentUserRank = null;

        if ($currentUser instanceof User) {
            if ($selectedArtist instanceof Artist) {
                $currentFandom = $userFandomRepository->findOneForUserAndArtist($currentUser, $selectedArtist);
                $currentUserRank = $currentFandom instanceof UserFandom ? $userFandomRepository->getRankPosition($currentFandom) : null;
            } else {
                $currentUserRank = $userRepository->getGlobalRankPosition($currentUser);
            }
        }

        return $this->render('front/leaderboard/leaderboard.html.twig', [
            'podium' => $podium,
            'others' => $others,
            'contextName' => $contextName,
            'mode' => $mode,
            'artists' => $entityManager->getRepository(Artist::class)->findBy(['isActive' => true], ['name' => 'ASC'], 12),
            'selectedArtist' => $selectedArtist,
            'currentUser' => $currentUser,
            'currentUserRank' => $currentUserRank,
            'playerCount' => $playerCount,
        ]);
    }

    /**
     * @return array{userId:int|null,displayName:string,xp:int,level:int}
     */
    private function normalizeGlobalRanking(User $user): array
    {
        return [
            'userId' => $user->getId(),
            'displayName' => $user->getDisplayName() ?? 'Fan',
            'xp' => $user->getGlobalXp(),
            'level' => $user->getGlobalLevel(),
        ];
    }

    /**
     * @return array{userId:int|null,displayName:string,xp:int,level:int}
     */
    private function normalizeFandomRanking(UserFandom $fandom): array
    {
        $user = $fandom->getUser();

        return [
            'userId' => $user?->getId(),
            'displayName' => $user?->getDisplayName() ?? 'Fan',
            'xp' => $fandom->getXp(),
            'level' => $fandom->getLevel(),
        ];
    }
}
