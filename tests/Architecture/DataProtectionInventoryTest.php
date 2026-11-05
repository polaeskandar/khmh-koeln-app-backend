<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Library\Types\Model\Type;
use App\Tests\Support\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DataProtectionInventoryTest extends KernelTestCase
{
    public function testEveryTableHasAnInventoryRow(): void
    {
        $inventory = (string) file_get_contents(dirname(__DIR__, levels: 2) . '/docs/data-protection.md');

        $tables = new Database(self::getContainer())
            ->connection()
            ->fetchFirstColumn(
                "SELECT schemaname || '.' || tablename FROM pg_tables
             WHERE schemaname NOT IN ('pg_catalog', 'information_schema') AND tablename NOT LIKE '%migration_versions'",
            );

        foreach (array_map(static fn (mixed $table): string => Type::of($table)->toString(), $tables) as $table) {
            self::assertStringContainsString(
                '`' . $table . '`',
                $inventory,
                $table . ' has no row in docs/data-protection.md',
            );
        }
    }
}
