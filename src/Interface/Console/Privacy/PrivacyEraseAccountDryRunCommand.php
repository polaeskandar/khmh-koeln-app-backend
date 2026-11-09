<?php

declare(strict_types=1);

namespace App\Interface\Console\Privacy;

use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Platform\Doctrine\Contract\TransactionsInterface;
use App\Workflow\Privacy\EraseAccountWorkflow;
use App\Workflow\Privacy\Model\ErasureCounts;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:privacy:erase-account:dry-run',
    description: 'Show what erasing an account would change, then change nothing',
)]
final readonly class PrivacyEraseAccountDryRunCommand
{
    public function __construct(
        private EraseAccountWorkflow $eraseAccount,
        private TransactionsInterface $transactions,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument(description: 'The account id')] string $account): int
    {
        try {
            $counts = $this->transactions->rehearse(fn (): ErasureCounts => ($this->eraseAccount)($account));
        } catch (NotFound|RuleViolation $refused) {
            $io->error($refused->getMessage());

            return Command::FAILURE;
        }

        $io->success('Dry run, nothing changed: ' . $counts->summary());

        return Command::SUCCESS;
    }
}
