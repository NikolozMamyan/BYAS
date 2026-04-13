<?php

namespace App\Repository;

use App\Entity\PublicPassportContactIntent;
use App\Entity\UserProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicPassportContactIntent>
 */
class PublicPassportContactIntentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicPassportContactIntent::class);
    }

    public function countForProfile(UserProfile $profile): int
    {
        return (int) $this->createQueryBuilder('intent')
            ->select('COUNT(intent.id)')
            ->andWhere('intent.profile = :profile')
            ->setParameter('profile', $profile)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return PublicPassportContactIntent[]
     */
    public function findRecentForProfile(UserProfile $profile, int $limit = 10): array
    {
        return $this->createQueryBuilder('intent')
            ->andWhere('intent.profile = :profile')
            ->setParameter('profile', $profile)
            ->orderBy('intent.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
