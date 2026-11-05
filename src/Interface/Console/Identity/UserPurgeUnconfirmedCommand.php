<?php

declare(strict_types=1);

namespace App\Interface\Console\Identity;

use App\Library\Strings\Model\Text;
use App\Workflow\Identity\PurgeUnconfirmedAccountsWorkflow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:purge-unconfirmed',
    description: 'Delete registrations whose email address was not confirmed in time (run daily)',
)]
final readonly class UserPurgeUnconfirmedCommand
{
    public function __construct(
        private PurgeUnconfirmedAccountsWorkflow $purgeUnconfirmedAccounts,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $deleted = ($this->purgeUnconfirmedAccounts)();
        $io->success(Text::format('Deleted %d unconfirmed registrations.', $deleted)->toString());

        return Command::SUCCESS;
    }
}
