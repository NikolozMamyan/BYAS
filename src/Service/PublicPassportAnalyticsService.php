<?php

namespace App\Service;

use App\Entity\PublicPassportContactIntent;
use App\Entity\PublicPassportVisit;
use App\Entity\User;
use App\Entity\UserProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class PublicPassportAnalyticsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function recordVisit(UserProfile $profile, Request $request, ?User $viewer): void
    {
        if ($viewer !== null && $profile->getUser() === $viewer) {
            return;
        }

        $visit = (new PublicPassportVisit())
            ->setProfile($profile)
            ->setViewer($viewer)
            ->setIpHash($this->hashNullable($request->getClientIp()))
            ->setUserAgentHash($this->hashNullable($request->headers->get('User-Agent')))
            ->setReferrer($this->trimNullable($request->headers->get('referer'), 255));

        $this->entityManager->persist($visit);
        $this->entityManager->flush();
    }

    public function recordContactIntent(UserProfile $profile, ?User $sender): void
    {
        if ($sender !== null && $profile->getUser() === $sender) {
            return;
        }

        $intent = (new PublicPassportContactIntent())
            ->setProfile($profile)
            ->setSender($sender);

        $this->entityManager->persist($intent);
        $this->entityManager->flush();
    }

    private function hashNullable(?string $value): ?string
    {
        $value = $this->trimNullable($value, 512);

        return $value !== null ? hash('sha256', $value) : null;
    }

    private function trimNullable(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? mb_substr($value, 0, $maxLength) : null;
    }
}
