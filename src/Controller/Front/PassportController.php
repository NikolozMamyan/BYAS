<?php

namespace App\Controller\Front;

use App\Entity\StreamingAccount;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\XpEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/app', name: 'app_front_')]
class PassportController extends AbstractController
{
    #[Route('/passport', name: 'passport', methods: ['GET'])]
    public function show(UserRepository $userRepository, XpEngine $xpEngine): Response
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

        return $this->render('front/passport/show.html.twig', [
            'user' => $user,
            'profile' => $user->getProfile(),
            'fandoms' => $user->getUserFandoms(),
            'oauthAccounts' => $user->getOauthAccounts(),
            'streamingAccounts' => $streamingAccounts,
            'spotifyAccount' => $spotifyAccount,
            'isSpotifyConnected' => $isSpotifyConnected,
            'xpTransactions' => $user->getXpTransactions(),
            'items' => $user->getCollectionItems(),
            'userBadges' => $user->getUserBadges(),
            'globalRank' => $userRepository->getGlobalRankPosition($user),
            'globalProgress' => $xpEngine->progressForXp($user->getGlobalXp()),
        ]);
    }
}
