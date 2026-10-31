<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Doctrine\Service;

use App\Library\Time\Model\LocalTime;
use App\Platform\Doctrine\Service\LocalTimeType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalTimeTypeTest extends KernelTestCase
{
    #[DataProvider('sessionZones')]
    public function testAValueComesBackUnchangedInEverySessionZone(string $zone): void
    {
        $connection = $this->connection();
        $type = new LocalTimeType();
        $platform = $connection->getDatabasePlatform();
        $connection->executeStatement(sprintf("SET LOCAL TIME ZONE '%s'", $zone));
        $connection->executeStatement(sprintf('CREATE TEMPORARY TABLE local_time_probe (value %s)', $type->getSQLDeclaration(
            [],
            $platform,
        )));

        $connection->insert('local_time_probe', ['value' => LocalTime::of('02:30')], ['local_time']);
        $read = $type->convertToPHPValue($connection->fetchOne('SELECT value FROM local_time_probe'), $platform);

        self::assertSame('02:30', $read?->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sessionZones(): iterable
    {
        yield 'UTC' => ['UTC'];
        yield 'Berlin' => ['Europe/Berlin'];
    }

    public function testTheTypeIsRegisteredAndKeepsNull(): void
    {
        $platform = $this->connection()->getDatabasePlatform();
        $type = Type::getType('local_time');

        self::assertInstanceOf(LocalTimeType::class, $type);
        self::assertNull($type->convertToPHPValue(null, $platform));
        self::assertNull($type->convertToDatabaseValue(null, $platform));
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
