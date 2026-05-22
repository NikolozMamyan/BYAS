<?php

namespace App\Controller\Front;

use App\Entity\Artist;
use App\Entity\User;
use App\Entity\UserFandom;
use App\Repository\UserFandomRepository;
use App\Repository\UserRepository;
use App\Service\LevelBadgeCatalog;
use App\Service\PublicPassportProfileService;
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
        PublicPassportProfileService $publicPassportProfileService,
        LevelBadgeCatalog $levelBadgeCatalog,
    ): Response
    {
        $artistSlug = $request->query->get('artist');
        $selectedArtist = is_string($artistSlug) && trim($artistSlug) !== ''
            ? $entityManager->getRepository(Artist::class)->findOneBy(['slug' => trim($artistSlug)])
            : null;

        if ($selectedArtist instanceof Artist) {
            $rankings = array_map(
                fn (UserFandom $fandom): array => $this->normalizeFandomRanking($fandom, $publicPassportProfileService, $levelBadgeCatalog),
                $userFandomRepository->findTopByArtist($selectedArtist, 50)
            );
            $playerCount = count($rankings);
            $contextName = $selectedArtist->getName() ?? 'Fandom';
            $mode = 'Fandom';
        } else {
            $rankings = array_map(
                fn (User $user): array => $this->normalizeGlobalRanking($user, $publicPassportProfileService, $levelBadgeCatalog),
                $userRepository->findTopByGlobalXp(50)
            );
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
     * @return array{userId:int|null,displayName:string,xp:int,level:int,levelBadge:array{level:int,mainTitle:string,subLevelTitle:?string,fullTitle:string},profileUrl:string|null,avatarUrl:string|null}
     */
    private function normalizeGlobalRanking(
        User $user,
        PublicPassportProfileService $publicPassportProfileService,
        LevelBadgeCatalog $levelBadgeCatalog,
    ): array
    {
        $profile = $publicPassportProfileService->ensureProfile($user);

        return [
            'userId' => $user->getId(),
            'displayName' => $profile->getUsername() ?? $user->getDisplayName() ?? 'Fan',
            'xp' => $user->getGlobalXp(),
            'level' => $user->getGlobalLevel(),
            'levelBadge' => $levelBadgeCatalog->forLevel($user->getGlobalLevel()),
            'profileUrl' => $this->generateUrl('app_public_user_profile', ['id' => $user->getId()]),
            'avatarUrl' => $user->getAvatarUrl(),
        ];
    }

    /**
     * @return array{userId:int|null,displayName:string,xp:int,level:int,levelBadge:array{level:int,mainTitle:string,subLevelTitle:?string,fullTitle:string},profileUrl:string|null,avatarUrl:string|null}
     */
    private function normalizeFandomRanking(
        UserFandom $fandom,
        PublicPassportProfileService $publicPassportProfileService,
        LevelBadgeCatalog $levelBadgeCatalog,
    ): array
    {
        $user = $fandom->getUser();
        $profile = $user instanceof User ? $publicPassportProfileService->ensureProfile($user) : null;

        return [
            'userId' => $user?->getId(),
            'displayName' => $profile?->getUsername() ?? $user?->getDisplayName() ?? 'Fan',
            'xp' => $fandom->getXp(),
            'level' => $fandom->getLevel(),
            'levelBadge' => $levelBadgeCatalog->forLevel($fandom->getLevel()),
            'profileUrl' => $user instanceof User ? $this->generateUrl('app_public_user_profile', ['id' => $user->getId()]) : null,
            'avatarUrl' => $user?->getAvatarUrl(),
        ];
    }
}
