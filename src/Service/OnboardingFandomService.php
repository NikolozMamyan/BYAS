<?php

namespace App\Service;

use App\Entity\Artist;
use App\Entity\User;
use App\Entity\UserFandom;
use Doctrine\ORM\EntityManagerInterface;

final class OnboardingFandomService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array{name:string, fandom:string, slug:string, theme:string, image:?string} $selection
     */
    public function select(User $user, array $selection): UserFandom
    {
        $artistRepository = $this->entityManager->getRepository(Artist::class);
        $artist = $artistRepository->findOneBy(['slug' => $selection['slug']]);

        if (!$artist instanceof Artist) {
            $artist = (new Artist())
                ->setName($selection['name'])
                ->setSlug($selection['slug'])
                ->setType('group');
        }

        if ($selection['image'] !== null && trim((string) $artist->getCoverImageUrl()) === '') {
            $artist->setCoverImageUrl('img/choseGroup/' . $selection['image']);
        }

        $fandomRepository = $this->entityManager->getRepository(UserFandom::class);
        $userFandom = $fandomRepository->findOneBy([
            'user' => $user,
            'artist' => $artist,
        ]);

        if (!$userFandom instanceof UserFandom) {
            $now = new \DateTimeImmutable();
            $userFandom = (new UserFandom())
                ->setUser($user)
                ->setArtist($artist)
                ->setFirstEngagedAt($now)
                ->setProgressPercent(0.0)
                ->setUpdatedAt($now);

            $user->addUserFandom($userFandom);
        }

        $this->entityManager->persist($artist);
        $this->entityManager->persist($userFandom);
        $this->entityManager->flush();

        return $userFandom;
    }
}
