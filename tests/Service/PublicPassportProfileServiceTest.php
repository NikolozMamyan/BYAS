<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Service\PublicPassportProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class PublicPassportProfileServiceTest extends TestCase
{
    public function testEnsureProfileCreatesProfileAndShareSlug(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(UserProfile::class)->willReturn($repository);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(UserProfile::class));
        $entityManager->expects(self::once())->method('flush');

        $service = new PublicPassportProfileService($entityManager);
        $user = (new User())->setEmail('fan@example.com')->setDisplayName('Fan Name');

        $profile = $service->ensureProfile($user);

        self::assertSame($profile, $user->getProfile());
        self::assertSame('fan-name', $profile->getUsername());
        self::assertSame('fan-name', $profile->getShareSlug());
        self::assertTrue($profile->isProfilePublic());
    }

    public function testEnsureProfileKeepsExistingShareSlug(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $user = (new User())->setEmail('fan@example.com')->setDisplayName('Fan Name');
        $profile = (new UserProfile())
            ->setUser($user)
            ->setUsername('fan')
            ->setShareSlug('stable-slug');
        $user->setProfile($profile);

        $service = new PublicPassportProfileService($entityManager);

        self::assertSame($profile, $service->ensureProfile($user));
        self::assertSame('stable-slug', $profile->getShareSlug());
    }
}
