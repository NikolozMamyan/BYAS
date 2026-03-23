<?php

namespace App\Repository;

use App\Entity\StreamingAccount;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StreamingAccount>
 */
class StreamingAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StreamingAccount::class);
    }

    public function save(StreamingAccount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(StreamingAccount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByProviderAndProviderUserId(string $provider, string $providerUserId): ?StreamingAccount
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.provider = :provider')
            ->andWhere('sa.providerUserId = :providerUserId')
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->setParameter('providerUserId', trim($providerUserId))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByUserAndProvider(User $user, string $provider): ?StreamingAccount
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.user = :user')
            ->andWhere('sa.provider = :provider')
            ->setParameter('user', $user)
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return StreamingAccount[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.user = :user')
            ->setParameter('user', $user)
            ->orderBy('sa.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne seulement les comptes connectés pour un user.
     *
     * @return StreamingAccount[]
     */
    public function findConnectedByUser(User $user): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.user = :user')
            ->andWhere('sa.syncStatus = :status')
            ->setParameter('user', $user)
            ->setParameter('status', StreamingAccount::STATUS_CONNECTED)
            ->orderBy('sa.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return StreamingAccount[]
     */
    public function findByProvider(string $provider): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.provider = :provider')
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->orderBy('sa.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return StreamingAccount[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.syncStatus = :status')
            ->setParameter('status', mb_strtolower(trim($status)))
            ->orderBy('sa.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function existsForProviderAndProviderUserId(string $provider, string $providerUserId): bool
    {
        $count = (int) $this->createQueryBuilder('sa')
            ->select('COUNT(sa.id)')
            ->andWhere('sa.provider = :provider')
            ->andWhere('sa.providerUserId = :providerUserId')
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->setParameter('providerUserId', trim($providerUserId))
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function existsForUserAndProvider(User $user, string $provider): bool
    {
        $count = (int) $this->createQueryBuilder('sa')
            ->select('COUNT(sa.id)')
            ->andWhere('sa.user = :user')
            ->andWhere('sa.provider = :provider')
            ->setParameter('user', $user)
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Comptes techniquement expirés via expires_at.
     *
     * @return StreamingAccount[]
     */
    public function findExpiredAccounts(): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.expiresAt IS NOT NULL')
            ->andWhere('sa.expiresAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('sa.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Comptes qui expirent bientôt.
     *
     * @return StreamingAccount[]
     */
    public function findAccountsExpiringSoon(int $seconds = 300): array
    {
        $threshold = (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $seconds));

        return $this->createQueryBuilder('sa')
            ->andWhere('sa.expiresAt IS NOT NULL')
            ->andWhere('sa.expiresAt <= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('sa.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Comptes connectés avec refresh token disponible.
     *
     * @return StreamingAccount[]
     */
    public function findRefreshableConnectedAccounts(): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.syncStatus = :status')
            ->andWhere('sa.refreshToken IS NOT NULL')
            ->andWhere('sa.refreshToken != :empty')
            ->setParameter('status', StreamingAccount::STATUS_CONNECTED)
            ->setParameter('empty', '')
            ->orderBy('sa.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Comptes à resynchroniser selon une date de dernière sync.
     *
     * @return StreamingAccount[]
     */
    public function findAccountsNeedingSync(\DateTimeImmutable $before, ?string $provider = null): array
    {
        $qb = $this->createQueryBuilder('sa')
            ->andWhere('sa.syncStatus = :status')
            ->andWhere('(sa.lastSyncAt IS NULL OR sa.lastSyncAt <= :before)')
            ->setParameter('status', StreamingAccount::STATUS_CONNECTED)
            ->setParameter('before', $before)
            ->orderBy('sa.lastSyncAt', 'ASC');

        if ($provider !== null) {
            $qb->andWhere('sa.provider = :provider')
                ->setParameter('provider', mb_strtolower(trim($provider)));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Derniers comptes synchronisés.
     *
     * @return StreamingAccount[]
     */
    public function findRecentlySynced(int $limit = 20): array
    {
        return $this->createQueryBuilder('sa')
            ->andWhere('sa.lastSyncAt IS NOT NULL')
            ->orderBy('sa.lastSyncAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByProvider(string $provider): int
    {
        return (int) $this->createQueryBuilder('sa')
            ->select('COUNT(sa.id)')
            ->andWhere('sa.provider = :provider')
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('sa')
            ->select('COUNT(sa.id)')
            ->andWhere('sa.syncStatus = :status')
            ->setParameter('status', mb_strtolower(trim($status)))
            ->getQuery()
            ->getSingleScalarResult();
    }
}