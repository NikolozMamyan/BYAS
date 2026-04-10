<?php

namespace App\Command;

use App\Entity\Badge;
use App\Service\BadgeCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:badges:sync',
    description: 'Creates or updates the BYAS badge catalog without resetting other fixtures.',
)]
class SyncBadgesCommand extends Command
{
    public function __construct(
        private readonly BadgeCatalog $badgeCatalog,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $created = 0;
        $updated = 0;

        foreach ($this->badgeCatalog->all() as $code => $definition) {
            $badge = $this->entityManager->getRepository(Badge::class)->findOneBy(['code' => $code]);

            if (!$badge instanceof Badge) {
                $badge = new Badge();
                $badge->setCode($code);
                $created++;
            } else {
                $updated++;
            }

            $badge
                ->setName($definition['name'])
                ->setDescription($definition['description'])
                ->setScope($definition['scope'])
                ->setRuleType($definition['ruleType'])
                ->setRuleConfig($definition['ruleConfig'])
                ->setIsActive(true);

            $this->entityManager->persist($badge);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Badge catalog synced: %d created, %d updated.', $created, $updated));

        return Command::SUCCESS;
    }
}
