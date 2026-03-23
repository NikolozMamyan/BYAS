<?php

namespace App\Repository;

use App\Entity\StreamingAccount;
use App\Entity\StreamingPlayHistory;
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
}