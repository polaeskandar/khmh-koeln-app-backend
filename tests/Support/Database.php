<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class Database
{
    public const string UNIQUE_VIOLATION = '23505';

    public const string CHECK_VIOLATION = '23514';

    public const string RESTRICT_VIOLATION = '23001';

    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function entityManager(): EntityManagerInterface
    {
        $entityManager = $this->container->get(EntityManagerInterface::class);
        Assert::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    public function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }

    public function persisted(object ...$entities): void
    {
        $entityManager = $this->entityManager();

        foreach ($entities as $entity) {
            $entityManager->persist($entity);
        }

        $entityManager->flush();
    }

    /**
     * @param string|Closure(Connection): mixed $statement
     */
    public function assertRefused(string $sqlState, string|Closure $statement): void
    {
        $run = is_string($statement)
            ? static fn (Connection $connection): int|string => $connection->executeStatement($statement)
            : $statement;

        try {
            $this->connection()->transactional($run);
        } catch (DriverException $refusal) {
            Assert::assertSame($sqlState, $refusal->getSQLState(), $refusal->getMessage());

            return;
        }

        Assert::fail('The database accepted the statement.');
    }
}
