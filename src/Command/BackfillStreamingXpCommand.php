<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\StreamingPlayHistoryRepository;
use App\Repository\UserRepository;
use App\Service\XpEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:xp:backfill-streams',
    description: 'Awards XP for existing streaming play history rows that do not have an XP transaction yet.',
)]
class BackfillStreamingXpCommand extends Command
{
    public function __construct(
        private readonly StreamingPlayHistoryRepository $historyRepository,
        private readonly UserRepository $userRepository,
        private readonly XpEngine $xpEngine,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user-email', null, InputOption::VALUE_REQUIRED, 'Limit backfill to one user email.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows to read before each flush.', 100)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum rows to inspect.', null)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Inspect rows without writing XP transactions.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $limit = $input->getOption('limit') !== null ? max(1, (int) $input->getOption('limit')) : null;
        $dryRun = (bool) $input->getOption('dry-run');

        $user = $this->resolveUser($input, $io);
        if ($user === false) {
            return Command::FAILURE;
        }

        $lastId = 0;
        $inspected = 0;
        $awarded = 0;
        $skipped = 0;
        $xpAwarded = 0;

        do {
            $remaining = $limit !== null ? $limit - $inspected : $batchSize;
            if ($remaining <= 0) {
                break;
            }

            $histories = $this->historyRepository->findBatchAfterId(
                $user instanceof User ? $user : null,
                $lastId,
                min($batchSize, $remaining),
            );

            foreach ($histories as $history) {
                $lastId = $history->getId() ?? $lastId;
                $inspected++;

                if ($this->xpEngine->hasTransactionForSource(
                    XpEngine::SOURCE_STREAMING_PLAY,
                    $this->xpEngine->sourceReferenceForStreamingPlay($history),
                )) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $awarded++;
                    continue;
                }

                $transaction = $this->xpEngine->awardStreamingPlay($history);
                if ($transaction === null) {
                    $skipped++;
                    continue;
                }

                $awarded++;
                $xpAwarded += $transaction->getXpAmount();
            }

            if (!$dryRun && $awarded > 0) {
                $this->entityManager->flush();
            }
        } while ($histories !== []);

        $io->success(sprintf(
            'Backfill finished: %d inspected, %d awarded, %d skipped, +%d XP%s.',
            $inspected,
            $awarded,
            $skipped,
            $xpAwarded,
            $dryRun ? ' (dry-run)' : '',
        ));

        return Command::SUCCESS;
    }

    private function resolveUser(InputInterface $input, SymfonyStyle $io): User|false|null
    {
        $email = $input->getOption('user-email');

        if (!is_string($email) || trim($email) === '') {
            return null;
        }

        $user = $this->userRepository->findOneBy(['email' => mb_strtolower(trim($email))]);

        if (!$user instanceof User) {
            $io->error(sprintf('No user found for email "%s".', $email));

            return false;
        }

        return $user;
    }
}
