<?php

declare(strict_types=1);

namespace App\Platform\Migration\Command;

use App\Library\Strings\Model\Text;
use App\Platform\Migration\Model\MigrationSet;
use App\Platform\Migration\Service\MigrationRunner;
use Doctrine\Migrations\Generator\Exception\NoChangesDetected;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:migrations:diff',
    description: 'Generate a migration from the changes to one context\'s mapping',
)]
final readonly class DiffCommand
{
    public function __construct(
        private MigrationRunner $runner,
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        OutputInterface $output,
        #[Argument('The context, e.g. "people"')]
        string $context,
    ): int {
        try {
            return $this->runner->diff(MigrationSet::named($context, projectDir: $this->projectDir), $output);
        } catch (NoChangesDetected) {
            $io->success(Text::format('The "%s" context needs no migration.', $context)->toString());

            return Command::SUCCESS;
        }
    }
}
