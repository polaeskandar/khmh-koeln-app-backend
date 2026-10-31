<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Organization\Domain\Entity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Assert;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class OrganizationDatabase
{
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

    public function persisted(object ...$entities): void
    {
        $entityManager = $this->entityManager();
        foreach ($entities as $entity) {
            $entityManager->persist($entity);
        }

        $entityManager->flush();
    }

    public function assertRefused(string $sqlState, string $sql): void
    {
        try {
            $this->connection()->transactional(
                static fn (Connection $connection): int|string => $connection->executeStatement($sql),
            );
        } catch (DriverException $refusal) {
            Assert::assertSame($sqlState, $refusal->getSQLState(), $refusal->getMessage());

            return;
        }

        Assert::fail(sprintf('The database accepted: %s', $sql));
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
