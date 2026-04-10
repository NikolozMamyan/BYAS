<?php

namespace App\Controller\Front;

use App\Entity\StreamingAccount;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\XpTransactionRepository;
use App\Service\XpEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app', name: 'app_front_')]
class PassportController extends AbstractController
{
    #[Route('/passport', name: 'passport', methods: ['GET'])]
    public function show(
        UserRepository $userRepository,
        XpEngine $xpEngine,
        XpTransactionRepository $xpTransactionRepository,
    ): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $streamingAccounts = $user->getStreamingAccounts();

        $spotifyAccount = null;
        $isSpotifyConnected = false;

        foreach ($streamingAccounts as $streamingAccount) {
            if (
                $streamingAccount->getProvider() === StreamingAccount::PROVIDER_SPOTIFY
            ) {
                $spotifyAccount = $streamingAccount;
                $isSpotifyConnected = $streamingAccount->isConnected();
                break;
            }
        }

        $fandoms = $user->getUserFandoms()->toArray();
        usort($fandoms, static fn ($a, $b): int => $b->getXp() <=> $a->getXp());

        $userBadges = $user->getUserBadges()->toArray();
        usort(
            $userBadges,
            static fn ($a, $b): int => $b->getAwardedAt()->getTimestamp() <=> $a->getAwardedAt()->getTimestamp()
        );

        return $this->render('front/passport/show.html.twig', [
            'user' => $user,
            'profile' => $user->getProfile(),
            'fandoms' => $fandoms,
            'oauthAccounts' => $user->getOauthAccounts(),
            'streamingAccounts' => $streamingAccounts,
            'spotifyAccount' => $spotifyAccount,
            'isSpotifyConnected' => $isSpotifyConnected,
            'xpTransactions' => $xpTransactionRepository->findRecentForUser($user, 8),
            'items' => $user->getCollectionItems(),
            'userBadges' => $userBadges,
            'globalRank' => $userRepository->getGlobalRankPosition($user),
            'globalProgress' => $xpEngine->progressForXp($user->getGlobalXp()),
        ]);
    }
}
