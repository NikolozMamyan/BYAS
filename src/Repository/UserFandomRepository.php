<?php

namespace App\Repository;

use App\Entity\Artist;
use App\Entity\User;
use App\Entity\UserFandom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserFandom>
 */
class UserFandomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserFandom::class);
    }

    public function findOneForUserAndArtist(User $user, Artist $artist): ?UserFandom
    {
        return $this->createQueryBuilder('uf')
            ->andWhere('uf.user = :user')
            ->andWhere('uf.artist = :artist')
            ->setParameter('user', $user)
            ->setParameter('artist', $artist)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return UserFandom[]
     */
    public function findTopByArtist(Artist $artist, int $limit = 50): array
    {
        return $this->createQueryBuilder('uf')
            ->addSelect('u')
            ->join('uf.user', 'u')
            ->andWhere('uf.artist = :artist')
            ->setParameter('artist', $artist)
            ->orderBy('uf.xp', 'DESC')
            ->addOrderBy('uf.lastXpAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getRankPosition(UserFandom $fandom): ?int
    {
        if (!$fandom->getArtist() instanceof Artist) {
            return null;
        }

        return (int) $this->createQueryBuilder('uf')
            ->select('COUNT(uf.id) + 1')
            ->andWhere('uf.artist = :artist')
            ->andWhere('uf.xp > :xp')
            ->setParameter('artist', $fandom->getArtist())
            ->setParameter('xp', $fandom->getXp())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
