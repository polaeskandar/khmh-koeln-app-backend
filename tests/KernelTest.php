<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Smoke test for the project setup: the kernel boots in the test environment
 * and Doctrine is configured for PostgreSQL. It does not need a running database.
 */
final class KernelTest extends KernelTestCase
{
    public function testTheApplicationBootsWithAPostgreSqlConnection(): void
    {
        self::bootKernel();

        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        self::assertInstanceOf(Connection::class, $connection);
        self::assertInstanceOf(PostgreSQLPlatform::class, $connection->getDatabasePlatform());
    }
}
