<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class LoginAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST')
            && '/api/login' === $request->getPathInfo()
            && str_contains((string) $request->headers->get('Content-Type'), 'application/json');
    }

    public function authenticate(Request $request): Passport
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            throw new CustomUserMessageAuthenticationException('Invalid JSON payload.');
        }

        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '') {
            throw new CustomUserMessageAuthenticationException('Email is required.');
        }

        if ($password === '') {
            throw new CustomUserMessageAuthenticationException('Password is required.');
        }

        $request->getSession()->set('_security.last_username', $email);

        return new Passport(
            new UserBadge($email, function (string $userIdentifier): UserInterface {
                $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);

                if (!$user instanceof User) {
                    throw new CustomUserMessageAuthenticationException('Invalid credentials.');
                }

                if (!$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('Your account is disabled.');
                }

                return $user;
            }),
            new PasswordCredentials($password)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();

        return new JsonResponse([
            'message' => 'Authentication successful.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'displayName' => $user->getDisplayName(),
                'avatarUrl' => $user->getAvatarUrl(),
                'globalXp' => $user->getGlobalXp(),
                'globalLevel' => $user->getGlobalLevel(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
            ],
        ], Response::HTTP_OK);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'message' => 'Authentication required.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}