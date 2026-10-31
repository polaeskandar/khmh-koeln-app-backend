<?php

declare(strict_types=1);

namespace App\Platform\Migration\Command;

use App\Platform\Migration\Model\MigrationSet;
use App\Platform\Migration\Service\MigrationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:migrations:check', description: 'Fail when the database of a context differs from its mapping')]
final readonly class CheckCommand
{
    public function __construct(
        private MigrationRunner $runner,
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $outOfSync = MigrationSet::all($this->projectDir)->filter($this->isOutOfSync(...))->map(self::schemaOf(...));

        if ($outOfSync->isEmpty()) {
            $io->success('Every context matches its mapping.');

            return Command::SUCCESS;
        }

        $io->error([
            'These contexts differ from their mapping; generate a migration with app:migrations:diff <context>:',
            ...$outOfSync->getValues(),
        ]);

        return Command::FAILURE;
    }

    private function isOutOfSync(MigrationSet $set): bool
    {
        return !$this->runner->isInSync($set);
    }

    private static function schemaOf(MigrationSet $set): string
    {
        return $set->schema;
    }
}
