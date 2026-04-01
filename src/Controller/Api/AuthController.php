<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\DeviceIdentifier;
use App\Service\SessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
final class AuthController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        SessionManager $sessionManager
    ): JsonResponse {
        $data = $this->getRequestData($request);

        $email = $this->normalizeEmail($data['email'] ?? null);
        $password = $data['password'] ?? null;
        $displayName = $data['displayName'] ?? null;

        if (!$email || !$password) {
            return $this->jsonError('Email and password are required', 400);
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            return $this->jsonError('Email already in use', 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($displayName ?: null);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        [$session, $plainToken, $deviceId] = $sessionManager->createSession($user);

        return $this->authenticatedResponse(
            request: $request,
            message: 'Registration successful',
            user: $user,
            plainToken: $plainToken,
            deviceId: $deviceId,
            expiresAt: $session->getExpiresAt(),
            status: 201
        );
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        SessionManager $sessionManager
    ): JsonResponse {
        $data = $this->getRequestData($request);

        $email = $this->normalizeEmail($data['email'] ?? null);
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->jsonError('Email and password are required', 400);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->jsonError('Invalid credentials', 401);
        }

        try {
            [$session, $plainToken, $finalDeviceId] = $sessionManager->createSession($user);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'error' => 'Login blocked',
                'message' => $e->getMessage(),
            ], 403);
        }

        return $this->authenticatedResponse(
            request: $request,
            message: 'Login successful',
            user: $user,
            plainToken: $plainToken,
            deviceId: $finalDeviceId,
            expiresAt: $session->getExpiresAt()
        );
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(
        Request $request,
        SessionManager $sessionManager
    ): JsonResponse {
        $plainToken = $request->cookies->get('AUTH_TOKEN');

        if (!$plainToken) {
            return $this->jsonError('Missing authentication token', 401);
        }

        $session = $sessionManager->findActiveSessionByPlainToken($plainToken);

        $response = new JsonResponse([
            'message' => 'Logout successful',
        ]);

        if ($session) {
            $sessionManager->revoke($session, 'logout');
        }

        $response->headers->clearCookie('AUTH_TOKEN', '/');
        $response->headers->clearCookie('PHPSESSID', '/');

        return $response;
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->jsonError('Not authenticated', 401);
        }

        return new JsonResponse($this->serializeUser($user));
    }

    private function getRequestData(Request $request): array
    {
        $contentType = $request->headers->get('Content-Type', '');

        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($request->getContent(), true);

            return is_array($data) ? $this->normalizeInputKeys($data) : [];
        }

        return $this->normalizeInputKeys($request->request->all());
    }

    private function normalizeInputKeys(array $data): array
    {
        if (isset($data['_username']) && !isset($data['email'])) {
            $data['email'] = $data['_username'];
        }

        if (isset($data['_password']) && !isset($data['password'])) {
            $data['password'] = $data['_password'];
        }

        return $data;
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (!$email) {
            return null;
        }

        $email = trim(mb_strtolower($email));

        return $email !== '' ? $email : null;
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'roles' => $user->getRoles(),
        ];
    }

    private function authenticatedResponse(
        Request $request,
        string $message,
        User $user,
        string $plainToken,
        string $deviceId,
        \DateTimeInterface $expiresAt,
        int $status = 200
    ): JsonResponse {
        $response = new JsonResponse([
            'message' => $message,
            'user' => $this->serializeUser($user),
            'session' => [
                'expiresAt' => $expiresAt->format(DATE_ATOM),
            ],
        ], $status);

        $response->headers->setCookie($this->buildAuthCookie($request, $plainToken, $expiresAt));
        $response->headers->setCookie($this->buildDeviceCookie($request, $deviceId));

        return $response;
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => $message,
        ], $status);
    }

    private function buildAuthCookie(
        Request $request,
        string $plainToken,
        \DateTimeInterface $expiresAt
    ): Cookie {
        return Cookie::create('AUTH_TOKEN')
            ->withValue($plainToken)
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite('lax')
            ->withPath('/')
            ->withExpires($expiresAt);
    }

    private function buildDeviceCookie(Request $request, string $deviceId): Cookie
    {
        return Cookie::create(DeviceIdentifier::COOKIE_NAME)
            ->withValue($deviceId)
            ->withHttpOnly(true)
            ->withSecure($request->isSecure())
            ->withSameSite('lax')
            ->withPath('/')
            ->withExpires(new \DateTimeImmutable('+5 years'));
    }
}