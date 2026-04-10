<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\XpTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<XpTransaction>
 */
class XpTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, XpTransaction::class);
    }

    public function existsForSource(string $sourceType, ?string $sourceReference): bool
    {
        if ($sourceReference === null || trim($sourceReference) === '') {
            return false;
        }

        $count = (int) $this->createQueryBuilder('xt')
            ->select('COUNT(xt.id)')
            ->andWhere('xt.sourceType = :sourceType')
            ->andWhere('xt.sourceReference = :sourceReference')
            ->setParameter('sourceType', $sourceType)
            ->setParameter('sourceReference', $sourceReference)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @return XpTransaction[]
     */
    public function findRecentForUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('xt')
            ->addSelect('a')
            ->leftJoin('xt.artist', 'a')
            ->andWhere('xt.user = :user')
            ->setParameter('user', $user)
            ->orderBy('xt.occurredAt', 'DESC')
            ->addOrderBy('xt.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
