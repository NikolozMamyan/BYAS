<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthCookieService
{
    public function attachAuthenticationCookies(
        Response $response,
        Request $request,
        string $plainToken,
        string $deviceId,
        \DateTimeInterface $expiresAt
    ): void {
        $response->headers->setCookie($this->buildAuthCookie($request, $plainToken, $expiresAt));
        $response->headers->setCookie($this->buildDeviceCookie($request, $deviceId));
    }

    public function clearAuthenticationCookies(Response $response): void
    {
        $response->headers->clearCookie('AUTH_TOKEN', '/');
        $response->headers->clearCookie(DeviceIdentifier::COOKIE_NAME, '/');
        $response->headers->clearCookie('PHPSESSID', '/');
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
