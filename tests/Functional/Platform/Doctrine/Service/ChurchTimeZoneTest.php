<?php

declare(strict_types=1);

namespace App\Tests\Functional\Platform\Doctrine\Service;

use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChurchTimeZoneTest extends KernelTestCase
{
    public function testEveryDatabaseSessionRunsInTheChurchTimeZone(): void
    {
        $connection = new Database(self::getContainer())->connection();

        self::assertSame('Europe/Berlin', $connection->fetchOne("SELECT current_setting('TimeZone')"));

        self::assertSame(
            '2026-11-08 00:30:00+01',
            $connection->fetchOne("SELECT CAST('2026-11-07T23:30:00Z' AS timestamptz)::text"),
        );
    }
}
