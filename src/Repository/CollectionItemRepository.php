<?php

namespace App\Repository;

use App\Entity\CollectionItem;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CollectionItem>
 */
class CollectionItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CollectionItem::class);
    }

    public function save(CollectionItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CollectionItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return CollectionItem[]
     */
    public function findVisibleByUser(User $user): array
    {
        return $this->createQueryBuilder('ci')
            ->leftJoin('ci.type', 'type')->addSelect('type')
            ->leftJoin('ci.artist', 'artist')->addSelect('artist')
            ->andWhere('ci.user = :user')
            ->andWhere('ci.isPublic = :isPublic')
            ->setParameter('user', $user)
            ->setParameter('isPublic', true)
            ->orderBy('type.label', 'ASC')
            ->addOrderBy('ci.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return CollectionItem[]
     */
    public function findAllByUser(User $user): array
    {
        return $this->createQueryBuilder('ci')
            ->leftJoin('ci.type', 'type')->addSelect('type')
            ->leftJoin('ci.artist', 'artist')->addSelect('artist')
            ->andWhere('ci.user = :user')
            ->setParameter('user', $user)
            ->orderBy('type.label', 'ASC')
            ->addOrderBy('ci.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}