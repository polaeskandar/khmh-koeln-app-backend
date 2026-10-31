<?php

declare(strict_types=1);

namespace App\Platform\Migration\Command;

use App\Library\Strings\Model\Text;
use App\Platform\Migration\Model\MigrationSet;
use App\Platform\Migration\Service\MigrationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:migrations:migrate', description: 'Apply the migrations of every context')]
final readonly class MigrateCommand
{
    public function __construct(
        private MigrationRunner $runner,
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {
    }

    public function __invoke(SymfonyStyle $io, OutputInterface $output): int
    {
        foreach (MigrationSet::all($this->projectDir) as $set) {
            $io->section(Text::format('Context "%s" (schema "%s")', $set->context, $set->schema)->toString());

            $status = $this->runner->migrate($set, $output);
            if ($status !== Command::SUCCESS) {
                return $status;
            }
        }

        return Command::SUCCESS;
    }
}
