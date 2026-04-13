<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\UserSession;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class FunctionalWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        static::ensureKernelShutdown();
        $this->client = static::createClient();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $this->resetDatabase();
    }

    protected function createUser(
        string $email = 'fan@example.com',
        string $displayName = 'Test Fan',
        ?string $avatarUrl = null,
    ): User {
        $user = (new User())
            ->setEmail($email)
            ->setDisplayName($displayName)
            ->setPassword(null)
            ->setRoles(['ROLE_USER'])
            ->setAvatarUrl($avatarUrl);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function login(User $user): void
    {
        $plainToken = bin2hex(random_bytes(32));

        $session = (new UserSession())
            ->setUser($user)
            ->setTokenHash(hash('sha256', $plainToken))
            ->setDeviceId('test-device')
            ->setDeviceName('PHPUnit Device')
            ->setUserAgent('PHPUnit Browser')
            ->setIpAddress('127.0.0.1')
            ->setExpiresAt(new \DateTimeImmutable('+30 days'))
            ->setLastActivityAt(new \DateTimeImmutable());

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        $this->client->getCookieJar()->set(new Cookie('AUTH_TOKEN', $plainToken));
    }

    private function resetDatabase(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $connection = $this->entityManager->getConnection();
        $schemaTool = new SchemaTool($this->entityManager);

        if ($metadata === []) {
            return;
        }

        $isSqlite = $connection->getDatabasePlatform() instanceof SQLitePlatform;

        if ($isSqlite) {
            $connection->executeStatement('PRAGMA foreign_keys = OFF');
        }

        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        if ($isSqlite) {
            $connection->executeStatement('PRAGMA foreign_keys = ON');
        }
    }
}
