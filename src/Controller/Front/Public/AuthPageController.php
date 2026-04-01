<?php

namespace App\Controller\Front\Public;


use App\Entity\User;
use App\Service\SessionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class AuthPageController extends AbstractController
{
    #[Route('/login', name: 'show_login', methods: ['GET'])]
    public function login(Request $request,  Security $security): Response
    {
        $response = new Response();

          $user = $this->getUser(); 
        if($user) {
              $security->logout(false);
        }
    
        $response->setContent(
            $this->renderView('front/auth/login.html.twig')
        );
    
        return $response;
    }
    
    #[Route('/register', name: 'show_register', methods: ['GET'])]
    public function showRegister(Request $request,  Security $security): Response
    {
        $response = new Response();


          $user = $this->getUser(); 
        if($user) {
              $security->logout(false);
        }
    
        $response->setContent(
            $this->renderView('front/auth/register.html.twig')
        );
    
        return $response;
    }
    
      #[Route('/', name: 'app_landing', methods: ['GET'])]
public function landing(Request $request, Security $security): Response
{
     $response = new Response();
    $user = $this->getUser();

    if($user) {
              $security->logout(false);
        }

        $response->setContent(
            $this->renderView('front/landing/index.html.twig')
        );
    return $response;
}

       
    }




    