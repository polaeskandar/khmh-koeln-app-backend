<?php

declare(strict_types=1);

namespace App\Tests;

use App\Kernel;
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

    public function testItIsBuiltFromTheRuntimeContext(): void
    {
        $kernel = Kernel::fromContext(['APP_ENV' => 'test', 'APP_DEBUG' => '1']);

        self::assertSame('test', $kernel->getEnvironment());
        self::assertTrue($kernel->isDebug());
        self::assertFalse(Kernel::fromContext(['APP_ENV' => 'prod', 'APP_DEBUG' => '0'])->isDebug());
    }
}
