<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DatabaseIsolationTest extends KernelTestCase
{
    public function testAFirstTestCreatesTheTableAndRow(): void
    {
        $this->createProbeWithOneRow();

        self::assertSame(1, $this->probeCount());
    }

    public function testASecondTestCreatesTheSameTableAndRow(): void
    {
        $this->createProbeWithOneRow();

        self::assertSame(1, $this->probeCount());
    }

    public function testViolationsInSavepointsKeepTheConnectionUsable(): void
    {
        $this->createProbeWithOneRow();
        $connection = $this->connection();

        $duplicate =
            static fn () => $connection->transactional(static fn (Connection $c): int|string => $c->insert('isolation_probe', [
                'id' => 2,
                'name' => 'first',
            ]));

        $missingName =
            static fn () => $connection->transactional(static fn (Connection $c): int|string => $c->insert('isolation_probe', [
                'id' => 3,
                'name' => null,
            ]));

        self::assertThrowsViolation(UniqueConstraintViolationException::class, $duplicate);
        self::assertThrowsViolation(NotNullConstraintViolationException::class, $missingName);

        $connection->insert('isolation_probe', ['id' => 4, 'name' => 'second']);
        self::assertSame(2, $this->probeCount());
    }

    /**
     * @param class-string<\Throwable> $expected
     * @param \Closure(): mixed $statement
     */
    private static function assertThrowsViolation(string $expected, \Closure $statement): void
    {
        try {
            $statement();
        } catch (\Throwable $exception) {
            self::assertInstanceOf($expected, $exception);

            return;
        }

        self::fail(sprintf('Expected %s.', $expected));
    }

    private function createProbeWithOneRow(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('CREATE TABLE isolation_probe (id INT PRIMARY KEY, name TEXT NOT NULL UNIQUE)');
        $connection->insert('isolation_probe', ['id' => 1, 'name' => 'first']);
    }

    private function probeCount(): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM isolation_probe');
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
