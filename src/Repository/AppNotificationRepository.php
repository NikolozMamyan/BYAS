<?php

namespace App\Repository;

use App\Entity\AppNotification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppNotification>
 */
class AppNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppNotification::class);
    }

    /**
     * @return AppNotification[]
     */
    public function findRecentForUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('notification')
            ->andWhere('notification.user = :user')
            ->setParameter('user', $user)
            ->orderBy('notification.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('notification')
            ->select('COUNT(notification.id)')
            ->andWhere('notification.user = :user')
            ->andWhere('notification.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllReadForUser(User $user): void
    {
        $this->createQueryBuilder('notification')
            ->update()
            ->set('notification.isRead', ':read')
            ->set('notification.readAt', ':readAt')
            ->andWhere('notification.user = :user')
            ->andWhere('notification.isRead = false')
            ->setParameter('read', true)
            ->setParameter('readAt', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
