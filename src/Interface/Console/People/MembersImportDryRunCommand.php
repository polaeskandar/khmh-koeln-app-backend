<?php

declare(strict_types=1);

namespace App\Interface\Console\People;

use App\Workflow\People\Model\ImportReport;
use App\Workflow\People\PlanMemberImportWorkflow;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:members:import:dry-run',
    description: 'Show what importing a members CSV file would do, without writing anything',
)]
final readonly class MembersImportDryRunCommand
{
    private const string INVALID = 'Nothing was imported. Fix these lines and run the import again:';

    public function __construct(
        private PlanMemberImportWorkflow $planMemberImport,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The CSV file: first_name, last_name, display_name, service, stage')]
        string $file,
        #[Option(description: 'What to do with a row whose name an existing person has: skip or allow')]
        string $duplicates = 'skip',
    ): int {
        try {
            $report = ($this->planMemberImport)($file, $duplicates);
        } catch (InvalidArgumentException|RuntimeException $refused) {
            $io->error($refused->getMessage());

            return Command::FAILURE;
        }

        return self::shown($io, $report);
    }

    private static function shown(SymfonyStyle $io, ImportReport $report): int
    {
        if (!$report->isValid()) {
            $io->error(self::INVALID);
            $io->listing($report->errors);

            return Command::FAILURE;
        }

        foreach ($report->sections as [$title, $lines]) {
            $io->section($title);
            $io->listing($lines);
        }

        return Command::SUCCESS;
    }
}
