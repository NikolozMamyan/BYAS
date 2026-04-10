<?php

namespace App\Repository;

use App\Entity\PublicPassportVisit;
use App\Entity\UserProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PublicPassportVisit>
 */
class PublicPassportVisitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicPassportVisit::class);
    }

    public function countForProfile(UserProfile $profile): int
    {
        return (int) $this->createQueryBuilder('visit')
            ->select('COUNT(visit.id)')
            ->andWhere('visit.profile = :profile')
            ->setParameter('profile', $profile)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countForProfileSince(UserProfile $profile, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('visit')
            ->select('COUNT(visit.id)')
            ->andWhere('visit.profile = :profile')
            ->andWhere('visit.createdAt >= :since')
            ->setParameter('profile', $profile)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return PublicPassportVisit[]
     */
    public function findRecentForProfile(UserProfile $profile, int $limit = 5): array
    {
        return $this->createQueryBuilder('visit')
            ->andWhere('visit.profile = :profile')
            ->setParameter('profile', $profile)
            ->orderBy('visit.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
