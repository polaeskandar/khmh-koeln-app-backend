<?php

declare(strict_types=1);

namespace App\Platform\Migration\Service;

use App\Platform\Migration\Model\MigrationSet;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Provider\SchemaProvider;
use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\DoctrineCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class MigrationRunner
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function migrate(MigrationSet $set, OutputInterface $output): int
    {
        return $this->run(
            new MigrateCommand($this->dependencyFactory($set)),
            parameters: ['--allow-no-migration' => true],
            output: $output,
        );
    }

    public function diff(MigrationSet $set, OutputInterface $output): int
    {
        $command = new DiffCommand($this->dependencyFactory($set));

        return $this->run(
            $command,
            parameters: ['--namespace' => $set->namespace, '--formatted' => true],
            output: $output,
        );
    }

    private function dependencyFactory(MigrationSet $set): DependencyFactory
    {
        $factory = DependencyFactory::fromEntityManager(new ConfigurationArray([
            'table_storage' => ['table_name' => $set->versionTable()],
            'migrations_paths' => [$set->namespace => $set->directory],
            'all_or_nothing' => true,
            'transactional' => true,
            'organize_migrations' => 'none',
            'custom_template' => __DIR__ . '/migration.tpl',
        ]), new ExistingEntityManager($this->entityManager));
        $factory->setService(
            SchemaProvider::class,
            new ContextSchemaProvider($this->entityManager, schema: $set->schema),
        );

        return $factory;
    }

    /**
     * @param array<string, bool|string> $parameters
     */
    private function run(DoctrineCommand $command, array $parameters, OutputInterface $output): int
    {
        $input = new ArrayInput($parameters);
        $input->setInteractive(interactive: false);

        return $command->run($input, $output);
    }
}
