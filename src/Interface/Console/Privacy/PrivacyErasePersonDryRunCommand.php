<?php

declare(strict_types=1);

namespace App\Interface\Console\Privacy;

use App\Library\Problem\Exception\NotFound;
use App\Workflow\Privacy\RehearsePersonErasureWorkflow;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:privacy:erase-person:dry-run',
    description: 'Show what erasing a member would change, then change nothing',
)]
final readonly class PrivacyErasePersonDryRunCommand
{
    public function __construct(
        private RehearsePersonErasureWorkflow $rehearsePersonErasure,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument(description: 'The member (person) id')] string $person): int
    {
        try {
            $counts = ($this->rehearsePersonErasure)($person);
        } catch (NotFound $missing) {
            $io->error($missing->getMessage());

            return Command::FAILURE;
        }

        $io->success('Dry run, nothing changed: ' . $counts->summary());

        return Command::SUCCESS;
    }
}
