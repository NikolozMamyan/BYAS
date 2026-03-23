<?php

namespace App\Repository;

use App\Entity\OAuthAccount;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OAuthAccount>
 */
class OAuthAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OAuthAccount::class);
    }

    public function save(OAuthAccount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OAuthAccount $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByProviderAndProviderUserId(string $provider, string $providerUserId): ?OAuthAccount
    {
        return $this->createQueryBuilder('oa')
            ->andWhere('oa.provider = :provider')
            ->andWhere('oa.providerUserId = :providerUserId')
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->setParameter('providerUserId', trim($providerUserId))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByUserAndProvider(User $user, string $provider): ?OAuthAccount
    {
        return $this->createQueryBuilder('oa')
            ->andWhere('oa.user = :user')
            ->andWhere('oa.provider = :provider')
            ->setParameter('user', $user)
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return OAuthAccount[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('oa')
            ->andWhere('oa.user = :user')
            ->setParameter('user', $user)
            ->orderBy('oa.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return OAuthAccount[]
     */
    public function findByProvider(string $provider): array
    {
        return $this->createQueryBuilder('oa')
            ->andWhere('oa.provider = :provider')
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->orderBy('oa.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function existsForProviderAndProviderUserId(string $provider, string $providerUserId): bool
    {
        $count = (int) $this->createQueryBuilder('oa')
            ->select('COUNT(oa.id)')
            ->andWhere('oa.provider = :provider')
            ->andWhere('oa.providerUserId = :providerUserId')
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->setParameter('providerUserId', trim($providerUserId))
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function existsForUserAndProvider(User $user, string $provider): bool
    {
        $count = (int) $this->createQueryBuilder('oa')
            ->select('COUNT(oa.id)')
            ->andWhere('oa.user = :user')
            ->andWhere('oa.provider = :provider')
            ->setParameter('user', $user)
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Retourne les comptes OAuth expirés à l’instant T.
     *
     * @return OAuthAccount[]
     */
    public function findExpiredAccounts(): array
    {
        return $this->createQueryBuilder('oa')
            ->andWhere('oa.expiresAt IS NOT NULL')
            ->andWhere('oa.expiresAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('oa.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les comptes qui expirent bientôt.
     *
     * @return OAuthAccount[]
     */
    public function findAccountsExpiringSoon(int $seconds = 300): array
    {
        $threshold = (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $seconds));

        return $this->createQueryBuilder('oa')
            ->andWhere('oa.expiresAt IS NOT NULL')
            ->andWhere('oa.expiresAt <= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('oa.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return OAuthAccount[]
     */
    public function findWithRefreshTokenByProvider(string $provider): array
    {
        return $this->createQueryBuilder('oa')
            ->andWhere('oa.provider = :provider')
            ->andWhere('oa.refreshToken IS NOT NULL')
            ->andWhere('oa.refreshToken != :empty')
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->setParameter('empty', '')
            ->orderBy('oa.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByProvider(string $provider): int
    {
        return (int) $this->createQueryBuilder('oa')
            ->select('COUNT(oa.id)')
            ->andWhere('oa.provider = :provider')
            ->setParameter('provider', mb_strtolower(trim($provider)))
            ->getQuery()
            ->getSingleScalarResult();
    }
}