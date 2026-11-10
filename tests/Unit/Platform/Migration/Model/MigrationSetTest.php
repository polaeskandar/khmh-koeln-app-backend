<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Migration\Model;

use App\Platform\Migration\Model\MigrationSet;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MigrationSetTest extends TestCase
{
    public function testEachContextWithAMigrationsFolderIsASetInNameOrder(): void
    {
        $root = self::project('People', 'Meetings', 'Organization');
        mkdir($root . '/src/Context/Learning/Infrastructure/Persistence', recursive: true);
        touch($root . '/src/Context/notes.txt');

        $sets = MigrationSet::all($root);

        self::assertSame([0, 1, 2], $sets->getKeys());
        self::assertSame(['Meetings', 'Organization', 'People'], $sets->map(self::contextOf(...))->toArray());

        $people = $sets->last();
        self::assertInstanceOf(MigrationSet::class, $people);
        self::assertSame('people', $people->schema);
        self::assertSame('App\\Context\\People\\Infrastructure\\Persistence\\Migrations', $people->namespace);
        self::assertSame($root . '/src/Context/People/Infrastructure/Persistence/Migrations', $people->directory);
        self::assertSame('people_migration_versions', $people->versionTable());
    }

    public function testASetIsNamedByItsSchema(): void
    {
        $root = self::project('Identity', 'Privacy');

        self::assertSame('Privacy', MigrationSet::named('privacy', $root)->context);

        $this->expectExceptionObject(new InvalidArgumentException('Unknown context "Privacy".'));

        MigrationSet::named('Privacy', $root);
    }

    public function testAProjectWithoutContextsHasNoSets(): void
    {
        self::assertSame([], MigrationSet::all(self::project())->toArray());
    }

    private static function contextOf(MigrationSet $set): string
    {
        return $set->context;
    }

    private static function project(string ...$contexts): string
    {
        $root = sys_get_temp_dir() . '/migration-set-test-' . bin2hex(random_bytes(4));
        mkdir($root . '/src/Context', recursive: true);

        foreach ($contexts as $context) {
            mkdir($root . '/src/Context/' . $context . '/Infrastructure/Persistence/Migrations', recursive: true);
        }

        return $root;
    }
}
