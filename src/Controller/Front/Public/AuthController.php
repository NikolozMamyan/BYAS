<?php

namespace App\Controller\Front\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('', name: 'app_front_')]
class AuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_front_passport');
        }

        return $this->render('front/auth/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/login-check', name: 'login_check', methods: ['POST'])]
    public function loginCheck(): never
    {
        throw new \LogicException('This route is handled by Symfony form_login.');
    }

    #[Route('/logout', name: 'logout', methods: ['GET', 'POST'])]
    public function logout(): never
    {
        throw new \LogicException('This route is handled by Symfony logout.');
    }
}