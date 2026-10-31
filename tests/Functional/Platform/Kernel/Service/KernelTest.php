<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Kernel\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
