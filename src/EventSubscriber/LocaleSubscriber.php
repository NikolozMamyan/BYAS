<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_LOCALES = ['en', 'fr'];
    private const LOCALE_COOKIE_NAME = 'BYAS_LOCALE';

    private ?string $detectedLocale = null;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
            KernelEvents::RESPONSE => ['onKernelResponse', -20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->hasSession() ? $request->getSession() : null;

        if ($session === null) {
            return;
        }

        $locale = $session?->get('_locale');

        if ((!is_string($locale) || $locale === '') && $request->cookies->has(self::LOCALE_COOKIE_NAME)) {
            $cookieLocale = (string) $request->cookies->get(self::LOCALE_COOKIE_NAME, '');

            if (in_array($cookieLocale, self::ALLOWED_LOCALES, true)) {
                $locale = $cookieLocale;

                if ($session !== null) {
                    $session->set('_locale', $cookieLocale);
                }
            }
        }

        if (!is_string($locale) || $locale === '') {
            $preferredLocale = $request->getPreferredLanguage(self::ALLOWED_LOCALES);

            if (is_string($preferredLocale) && $preferredLocale !== '') {
                $locale = $preferredLocale;
                $this->detectedLocale = $preferredLocale;

                if ($session !== null) {
                    $session->set('_locale', $preferredLocale);
                }
            }
        }

        if (is_string($locale) && $locale !== '') {
            $request->attributes->set('_locale', $locale);
            $request->setDefaultLocale($locale);
            $request->setLocale($locale);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || $this->detectedLocale === null) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($request->cookies->get(self::LOCALE_COOKIE_NAME) === $this->detectedLocale) {
            $this->detectedLocale = null;

            return;
        }

        $response->headers->setCookie(
            Cookie::create(self::LOCALE_COOKIE_NAME)
                ->withValue($this->detectedLocale)
                ->withHttpOnly(false)
                ->withSecure($request->isSecure())
                ->withSameSite('lax')
                ->withPath('/')
                ->withExpires(new \DateTimeImmutable('+5 years'))
        );

        $this->detectedLocale = null;
    }
}
