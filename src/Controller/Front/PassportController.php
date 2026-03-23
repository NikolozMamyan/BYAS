<?php

namespace App\Controller\Front;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('app', name: 'app_front_')]
class PassportController extends AbstractController
{
    #[Route('/passport', name: 'passport', methods: ['GET'])]
    public function show(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        return $this->render('front/passport/show.html.twig', [
            'user' => $user,
            'profile' => $user->getProfile(),
            'fandoms' => $user->getUserFandoms(),
            'oauthAccounts' => $user->getOauthAccounts(),
            'streamingAccounts' => $user->getStreamingAccounts(),
            'xpTransactions' => $user->getXpTransactions(),
            'items' => $user->getCollectionItems(),
        ]);
    }
}