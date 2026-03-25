<?php

namespace App\Controller\Front\Public;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
 #[Route('/register', name: 'register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_front_passport');
        }

        $error = null;
        $old = [
            'displayName' => '',
            'email' => '',
        ];

        if ($request->isMethod('POST')) {
            $token = $request->request->get('_token');

            if (!$this->isCsrfTokenValid('register_form', $token)) {
                $error = 'Session invalide, merci de réessayer.';
            } else {
                $displayName = trim((string) $request->request->get('displayName'));
                $email = mb_strtolower(trim((string) $request->request->get('email')));
                $plainPassword = trim((string) $request->request->get('password'));

                $old['displayName'] = $displayName;
                $old['email'] = $email;

                if ($displayName === '' || $email === '' || $plainPassword === '') {
                    $error = 'Tous les champs sont obligatoires.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'L’adresse email est invalide.';
                } elseif (mb_strlen($plainPassword) < 6) {
                    $error = 'Le mot de passe doit contenir au moins 6 caractères.';
                } else {
                    $existingUser = $entityManager->getRepository(User::class)->findOneBy([
                        'email' => $email,
                    ]);

                    if ($existingUser) {
                        $error = 'Un compte existe déjà avec cet email.';
                    } else {
                        $user = new User();
                        $user->setDisplayName($displayName);
                        $user->setEmail($email);
                        $user->setRoles(['ROLE_USER']);
                        $user->setPassword(
                            $passwordHasher->hashPassword($user, $plainPassword)
                        );

                        $entityManager->persist($user);
                        $entityManager->flush();

                        $this->addFlash('success', 'Ton compte a bien été créé. Tu peux maintenant te connecter.');

                        return $this->redirectToRoute('app_front_login');
                    }
                }
            }
        }

        return $this->render('front/auth/register.html.twig', [
            'error' => $error,
            'old' => $old,
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