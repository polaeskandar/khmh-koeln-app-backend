<?php

declare(strict_types=1);

namespace App\Interface\Console\Privacy;

use App\Library\Problem\Exception\NotFound;
use App\Workflow\Privacy\ErasePersonWorkflow;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:privacy:erase-person',
    description: 'Erase a member and all their data (Art. 17); past session totals stay',
)]
final readonly class PrivacyErasePersonCommand
{
    public function __construct(
        private ErasePersonWorkflow $erasePerson,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument(description: 'The member (person) id')] string $person): int
    {
        try {
            $counts = ($this->erasePerson)($person);
        } catch (NotFound $missing) {
            $io->error($missing->getMessage());

            return Command::FAILURE;
        }

        $io->success('Erased: ' . $counts->summary());

        return Command::SUCCESS;
    }
}
