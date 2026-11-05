<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Doctrine\Service;

use App\Library\Problem\Exception\RuleViolation;
use App\Platform\Doctrine\Service\PostgresDemoDatabase;
use App\Tests\Support\Database;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PostgresDemoDatabaseTest extends KernelTestCase
{
    #[DataProvider('hosts')]
    public function testOnlyTheMachineAndTheComposeDatabaseAreLocal(string $host, string $expected): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_pgsql', 'host' => $host]);

        $database = new PostgresDemoDatabase(
            $connection,
            new Database(self::getContainer())->entityManager(),
            anyHost: false,
        );

        self::assertSame($expected === 'local', $database->isLocal());
    }

    public function testARemoteDatabaseIsRefusedBeforeAnythingIsPurgedOrLoaded(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_pgsql', 'host' => 'db.example.org']);
        $entityManager = new Database(self::getContainer())->entityManager();
        $database = new PostgresDemoDatabase($connection, $entityManager, anyHost: false);
        $loads = 0;

        try {
            $database->replace(static function () use (&$loads): void {
                $loads++;
            });

            self::fail('The remote database was not refused.');
        } catch (RuleViolation $refused) {
            self::assertSame('demo.localDatabaseOnly', $refused->getMessage());
        }

        self::assertSame(0, $loads);
        self::assertFalse($connection->isConnected());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function hosts(): iterable
    {
        yield 'localhost' => ['localhost', 'local'];
        yield 'loopback' => ['127.0.0.1', 'local'];
        yield 'compose service' => ['database', 'local'];
        yield 'remote' => ['db.example.org', 'remote'];
    }
}
