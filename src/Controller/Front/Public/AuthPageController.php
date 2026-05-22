<?php

namespace App\Controller\Front\Public;

use App\Entity\User;
use App\Service\PublicPassportProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthPageController extends AbstractController
{
    #[Route('/login', name: 'show_login', methods: ['GET'])]
    public function login(Request $request, PublicPassportProfileService $publicPassportProfileService): Response
    {
        if ($redirect = $this->redirectIfAuthenticated($request, $publicPassportProfileService)) {
            return $redirect;
        }

        return $this->render('front/auth/login.html.twig', [
            'intent' => $request->query->get('intent'),
            'passport' => $request->query->get('passport'),
            'next' => $request->query->get('next'),
        ]);
    }

    #[Route('/register', name: 'show_register', methods: ['GET'])]
    public function showRegister(Request $request, PublicPassportProfileService $publicPassportProfileService): Response
    {
        if ($redirect = $this->redirectIfAuthenticated($request, $publicPassportProfileService)) {
            return $redirect;
        }

        return $this->render('front/auth/register.html.twig', [
            'intent' => $request->query->get('intent'),
            'passport' => $request->query->get('passport'),
            'next' => $request->query->get('next'),
        ]);
    }

    #[Route('/', name: 'app_landing', methods: ['GET'])]
    public function landing(Request $request, PublicPassportProfileService $publicPassportProfileService): Response
    {
        if ($redirect = $this->redirectIfAuthenticated($request, $publicPassportProfileService)) {
            return $redirect;
        }

        return $this->render('front/landing/index.html.twig');
    }

    private function redirectIfAuthenticated(
        Request $request,
        PublicPassportProfileService $publicPassportProfileService,
    ): ?Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return null;
        }

        $next = $request->query->get('next');
        $safeNext = is_string($next) && str_starts_with($next, '/') && !str_starts_with($next, '//')
            ? $next
            : '/app/passport';

        if ($publicPassportProfileService->requiresOnboarding($user)) {
            return $this->redirect('/app/onboarding?next=' . rawurlencode($safeNext));
        }

        return $this->redirect($safeNext);
    }
}
