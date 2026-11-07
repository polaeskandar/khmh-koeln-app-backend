<?php

declare(strict_types=1);

namespace App\Interface\Console\Privacy;

use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Workflow\Privacy\EraseAccountWorkflow;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:privacy:erase-account',
    description: 'Erase an account (Art. 17); what it did for the church stays without it',
)]
final readonly class PrivacyEraseAccountCommand
{
    public function __construct(
        private EraseAccountWorkflow $eraseAccount,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument(description: 'The account id')] string $account): int
    {
        try {
            $counts = ($this->eraseAccount)($account);
        } catch (NotFound|RuleViolation $refused) {
            $io->error($refused->getMessage());

            return Command::FAILURE;
        }

        $io->success('Erased: ' . $counts->summary());

        return Command::SUCCESS;
    }
}
