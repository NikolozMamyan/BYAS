<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return User[]
     */
    public function findTopByGlobalXp(int $limit = 50): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.globalXp', 'DESC')
            ->addOrderBy('u.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getGlobalRankPosition(User $user): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id) + 1')
            ->andWhere('u.globalXp > :xp')
            ->andWhere('u.isActive = :active')
            ->setParameter('xp', $user->getGlobalXp())
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getCountryRankPosition(User $user): ?int
    {
        $countryCode = $user->getProfile()?->getCountryCode();

        if ($countryCode === null || trim($countryCode) === '') {
            return null;
        }

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id) + 1')
            ->join('u.profile', 'profile')
            ->andWhere('profile.countryCode = :countryCode')
            ->andWhere('u.globalXp > :xp')
            ->andWhere('u.isActive = :active')
            ->setParameter('countryCode', strtoupper($countryCode))
            ->setParameter('xp', $user->getGlobalXp())
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByEmailOrDisplayName(string $identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = :identifier OR LOWER(u.displayName) = :identifier')
            ->setParameter('identifier', mb_strtolower(trim($identifier)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
