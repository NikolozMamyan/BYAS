<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

class PublicPassportProfileService
{
    private AsciiSlugger $slugger;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->slugger = new AsciiSlugger();
    }

    public function ensureProfile(User $user): UserProfile
    {
        $profile = $user->getProfile();

        if (!$profile instanceof UserProfile) {
            $profile = new UserProfile();
            $profile
                ->setUser($user)
                ->setUsername($this->uniqueUsername($user));

            $user->setProfile($profile);
        }

        if ($profile->getShareSlug() === null || trim($profile->getShareSlug()) === '') {
            $profile->setShareSlug($this->uniqueShareSlug($user));
        }

        $profile->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return $profile;
    }

    private function uniqueUsername(User $user): string
    {
        return $this->uniqueProfileValue(
            field: 'username',
            base: $user->getDisplayName() ?? 'fan',
            fallbackPrefix: 'fan',
        );
    }

    private function uniqueShareSlug(User $user): string
    {
        return $this->uniqueProfileValue(
            field: 'shareSlug',
            base: $user->getDisplayName() ?? 'fan',
            fallbackPrefix: 'passport',
        );
    }

    private function uniqueProfileValue(string $field, string $base, string $fallbackPrefix): string
    {
        $slug = strtolower((string) $this->slugger->slug($base));
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = $fallbackPrefix;
        }

        $slug = substr($slug, 0, 40);
        $candidate = $slug;
        $attempt = 0;

        while ($this->entityManager->getRepository(UserProfile::class)->findOneBy([$field => $candidate]) instanceof UserProfile) {
            $attempt++;
            $suffix = '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            $candidate = substr($slug, 0, 40 - strlen($suffix)) . $suffix;

            if ($attempt > 10) {
                $candidate = sprintf('%s-%s', $fallbackPrefix, bin2hex(random_bytes(6)));
                break;
            }
        }

        return $candidate;
    }
}
