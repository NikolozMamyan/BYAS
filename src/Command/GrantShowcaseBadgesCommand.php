<?php

namespace App\Command;

use App\Entity\Badge;
use App\Entity\UserBadge;
use App\Repository\UserRepository;
use App\Service\BadgeCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:badges:grant-showcase',
    description: 'Grants the six BYAS feedback showcase badges to one user.',
)]
final class GrantShowcaseBadgesCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('user', InputArgument::REQUIRED, 'Exact email address or display name, for example SAVV.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = trim((string) $input->getArgument('user'));
        $user = $this->userRepository->findOneByEmailOrDisplayName($identifier);

        if ($user === null) {
            $io->error(sprintf('No user found with email or display name "%s".', $identifier));

            return Command::FAILURE;
        }

        $created = 0;
        foreach (BadgeCatalog::SHOWCASE_CODES as $code) {
            $badge = $this->entityManager->getRepository(Badge::class)->findOneBy(['code' => $code]);
            if (!$badge instanceof Badge) {
                $io->error(sprintf('Badge "%s" is missing. Run app:badges:sync first.', $code));

                return Command::FAILURE;
            }

            $existing = $this->entityManager->getRepository(UserBadge::class)->findOneBy([
                'user' => $user,
                'badge' => $badge,
            ]);
            if ($existing instanceof UserBadge) {
                continue;
            }

            $userBadge = (new UserBadge())
                ->setUser($user)
                ->setBadge($badge)
                ->setContextData(['source' => 'feedback_showcase']);

            $user->addUserBadge($userBadge);
            $this->entityManager->persist($userBadge);
            $created++;
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d showcase badges granted to %s.', $created, $user->getDisplayName()));

        return Command::SUCCESS;
    }
}
