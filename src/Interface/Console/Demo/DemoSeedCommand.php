<?php

declare(strict_types=1);

namespace App\Interface\Console\Demo;

use App\Library\Problem\Exception\RuleViolation;
use App\Workflow\Demo\SeedDemoDataWorkflow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When('dev')]
#[When('test')]
#[AsCommand(
    name: 'app:demo:seed',
    description: 'Replace the local data with the fictional demo accounts, people and schedules',
)]
final readonly class DemoSeedCommand
{
    public function __construct(
        private SeedDemoDataWorkflow $seedDemoData,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            ($this->seedDemoData)();
        } catch (RuleViolation $refused) {
            $io->error($refused->getMessage());

            return Command::FAILURE;
        }

        $io->success('Loaded the demo data. Every demo account signs in with DEMO_PASSWORD.');

        return Command::SUCCESS;
    }
}
