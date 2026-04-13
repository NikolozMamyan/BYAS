<?php

namespace App\Repository;

use App\Entity\StreamingAccount;
use App\Entity\StreamingPlayHistory;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class StreamingPlayHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StreamingPlayHistory::class);
    }

    public function save(StreamingPlayHistory $entity, bool $flush = false): void
    {
        $this->_em->persist($entity);

        if ($flush) {
            $this->_em->flush();
        }
    }

    public function exists(
        StreamingAccount $account,
        string $providerItemId,
        \DateTimeImmutable $playedAt
    ): bool {
        $count = (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.streamingAccount = :account')
            ->andWhere('h.providerItemId = :itemId')
            ->andWhere('h.playedAt = :playedAt')
            ->setParameter('account', $account)
            ->setParameter('itemId', $providerItemId)
            ->setParameter('playedAt', $playedAt)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function existsForAccountAndItemId(StreamingAccount $account, string $providerItemId): bool
    {
        $count = (int) $this->createQueryBuilder('h')
            ->select('COUNT(h.id)')
            ->andWhere('h.streamingAccount = :account')
            ->andWhere('h.providerItemId = :itemId')
            ->setParameter('account', $account)
            ->setParameter('itemId', $providerItemId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @return StreamingPlayHistory[]
     */
    public function findBatchAfterId(?User $user, int $lastId = 0, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('h')
            ->addSelect('u')
            ->join('h.user', 'u')
            ->andWhere('h.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('h.id', 'ASC')
            ->setMaxResults($limit);

        if ($user instanceof User) {
            $qb->andWhere('h.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }
}
