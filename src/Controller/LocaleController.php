<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    private const ALLOWED_LOCALES = ['en', 'fr'];
    private const LOCALE_COOKIE_NAME = 'BYAS_LOCALE';

    #[Route('/locale/switch', name: 'app_locale_switch', methods: ['POST'])]
    public function switch(Request $request): RedirectResponse
    {
        $locale = (string) $request->request->get('locale', 'en');
        $redirectTo = (string) $request->request->get('redirect_to', '/');

        if (!in_array($locale, self::ALLOWED_LOCALES, true)) {
            $locale = 'en';
        }

        $request->getSession()->set('_locale', $locale);

        if (!str_starts_with($redirectTo, '/') || str_starts_with($redirectTo, '//')) {
            $redirectTo = '/';
        }

        $response = $this->redirect($redirectTo);
        $response->headers->setCookie(
            Cookie::create(self::LOCALE_COOKIE_NAME)
                ->withValue($locale)
                ->withHttpOnly(false)
                ->withSecure($request->isSecure())
                ->withSameSite('lax')
                ->withPath('/')
                ->withExpires(new \DateTimeImmutable('+5 years'))
        );

        return $response;
    }
}
