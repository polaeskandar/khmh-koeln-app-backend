<?php

declare(strict_types=1);

namespace App\Interface\Console\Meetings;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\Text;
use App\Workflow\Meetings\GenerateSessionsWorkflow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sessions:generate',
    description: "Create today's and tomorrow's sessions of the active schedules; --date backfills a missed day",
)]
final readonly class SessionsGenerateCommand
{
    public function __construct(
        private GenerateSessionsWorkflow $generateSessions,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'The church day to generate from (YYYY-MM-DD), at most today')]
        ?string $date = null,
    ): int {
        try {
            $run = ($this->generateSessions)($date);
        } catch (InvalidInput $invalid) {
            $io->error(Text::format('date: %s', $invalid->fieldErrors['date']['code'] ?? 'checkFields')->toString());

            return Command::INVALID;
        }

        if ($run->skipped) {
            $io->note('Another run holds the generator lock; nothing to do.');

            return Command::SUCCESS;
        }

        $io->success(
            Text::format(
                'Created %d sessions, %d existed, %d schedules failed.',
                $run->created,
                $run->existing,
                $run->failed,
            )->toString(),
        );

        return $run->failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
