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
}
