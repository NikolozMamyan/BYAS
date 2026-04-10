<?php

namespace App\DataFixtures;

use App\Entity\Badge;
use App\Service\BadgeCatalog;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly BadgeCatalog $badgeCatalog,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach ($this->badgeCatalog->all() as $code => $definition) {
            $badge = $manager->getRepository(Badge::class)->findOneBy(['code' => $code]);

            if (!$badge instanceof Badge) {
                $badge = new Badge();
                $badge->setCode($code);
            }

            $badge
                ->setName($definition['name'])
                ->setDescription($definition['description'])
                ->setScope($definition['scope'])
                ->setRuleType($definition['ruleType'])
                ->setRuleConfig($definition['ruleConfig'])
                ->setIsActive(true);

            $manager->persist($badge);
        }

        $manager->flush();
    }
}
