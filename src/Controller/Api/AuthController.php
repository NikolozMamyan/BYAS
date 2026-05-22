<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthCookieService;
use App\Service\PublicPassportProfileService;
use App\Service\SessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        SessionManager $sessionManager,
        AuthCookieService $authCookieService,
        PublicPassportProfileService $publicPassportProfileService,
    ): JsonResponse {
        $data = $this->getRequestData($request);

        $email = $this->normalizeEmail($data['email'] ?? null);
        $password = $data['password'] ?? null;
        $displayName = trim((string) ($data['displayName'] ?? ''));

        if (!$email || !$password || $displayName === '') {
            return $this->jsonError('Display name, email and password are required', 400);
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            return $this->jsonError('Email already in use', 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($displayName);
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
            authCookieService: $authCookieService,
            publicPassportProfileService: $publicPassportProfileService,
            next: is_string($data['next'] ?? null) ? $data['next'] : null,
            status: 201
        );
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        SessionManager $sessionManager,
        AuthCookieService $authCookieService,
        PublicPassportProfileService $publicPassportProfileService,
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
            expiresAt: $session->getExpiresAt(),
            authCookieService: $authCookieService,
            publicPassportProfileService: $publicPassportProfileService,
            next: is_string($data['next'] ?? null) ? $data['next'] : null,
        );
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(
        Request $request,
        SessionManager $sessionManager,
        AuthCookieService $authCookieService,
    ): JsonResponse {
        $plainToken = $request->cookies->get(AuthCookieService::AUTH_COOKIE_NAME);

        $session = is_string($plainToken) && trim($plainToken) !== ''
            ? $sessionManager->findActiveSessionByPlainToken($plainToken)
            : null;

        $response = new JsonResponse([
            'message' => 'Logout successful',
        ]);

        if ($session) {
            $sessionManager->revoke($session, 'logout');
        }

        $authCookieService->clearAuthenticationCookies($response);

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
        AuthCookieService $authCookieService,
        PublicPassportProfileService $publicPassportProfileService,
        ?string $next = null,
        int $status = 200
    ): JsonResponse {
        $redirectTo = $this->resolvePostAuthRedirect($user, $publicPassportProfileService, $next);

        $response = new JsonResponse([
            'message' => $message,
            'user' => $this->serializeUser($user),
            'redirectTo' => $redirectTo,
            'session' => [
                'expiresAt' => $expiresAt->format(DATE_ATOM),
            ],
        ], $status);

        $authCookieService->attachAuthenticationCookies($response, $request, $plainToken, $deviceId, $expiresAt);

        return $response;
    }

    private function resolvePostAuthRedirect(
        User $user,
        PublicPassportProfileService $publicPassportProfileService,
        ?string $next
    ): string {
        $fallback = '/app/passport';
        $safeNext = is_string($next) && str_starts_with($next, '/') && !str_starts_with($next, '//')
            ? $next
            : $fallback;

        if ($publicPassportProfileService->requiresOnboarding($user)) {
            return sprintf('/app/onboarding?next=%s', rawurlencode($safeNext));
        }

        return $safeNext;
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => $message,
        ], $status);
    }
}
