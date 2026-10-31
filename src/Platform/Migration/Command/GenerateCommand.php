<?php

declare(strict_types=1);

namespace App\Platform\Migration\Command;

use App\Platform\Migration\Model\MigrationSet;
use App\Platform\Migration\Service\MigrationRunner;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:migrations:generate',
    description: 'Generate an empty migration in one context, for SQL the mapping cannot express',
)]
final readonly class GenerateCommand
{
    public function __construct(
        private MigrationRunner $runner,
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
    ) {
    }

    public function __invoke(OutputInterface $output, #[Argument('The context, e.g. "people"')] string $context): int
    {
        return $this->runner->generate(MigrationSet::named($context, projectDir: $this->projectDir), $output);
    }
}
