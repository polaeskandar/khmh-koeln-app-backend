<?php

declare(strict_types=1);

namespace App\Platform\Doctrine\Service;

use App\Library\Problem\Exception\RuleViolation;
use App\Platform\Doctrine\Contract\DemoDatabase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class PostgresDemoDatabase implements DemoDatabase
{
    private const array LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', 'database'];

    private const string PURGE = <<<'SQL'
        DO $$
        BEGIN
            EXECUTE (
                SELECT 'TRUNCATE ' || string_agg(format('%I.%I', schemaname, tablename), ', ') || ' CASCADE'
                FROM pg_tables
                WHERE schemaname NOT IN ('pg_catalog', 'information_schema', 'public')
                  AND NOT (schemaname = 'identity' AND tablename IN ('role', 'permission', 'role_permission'))
            );
        END
        $$
        SQL;

    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
        #[Autowire(env: 'bool:ALLOW_DEMO_SEED')]
        private bool $anyHost,
    ) {
    }

    #[Override]
    public function isLocal(): bool
    {
        return new ArrayCollection(self::LOCAL_HOSTS)->contains($this->connection->getParams()['host'] ?? 'localhost');
    }

    #[Override]
    public function replace(callable $load): void
    {
        if (!$this->anyHost && !$this->isLocal()) {
            throw new RuleViolation('demo.localDatabaseOnly');
        }

        $this->entityManager->wrapInTransaction(function () use ($load): void {
            $this->connection->executeStatement(self::PURGE);
            $this->entityManager->clear();
            $load();
        });
    }
}
