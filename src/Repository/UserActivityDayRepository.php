<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserActivityDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserActivityDay>
 */
final class UserActivityDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserActivityDay::class);
    }

    /**
     * @return UserActivityDay[]
     */
    public function findRecentForUser(User $user, int $limit = 370): array
    {
        return $this->createQueryBuilder('activity')
            ->andWhere('activity.user = :user')
            ->setParameter('user', $user)
            ->orderBy('activity.activityDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOldestSince(User $user, \DateTimeImmutable $since): ?UserActivityDay
    {
        return $this->createQueryBuilder('activity')
            ->andWhere('activity.user = :user')
            ->andWhere('activity.activityDate >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since->setTime(0, 0))
            ->orderBy('activity.activityDate', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
