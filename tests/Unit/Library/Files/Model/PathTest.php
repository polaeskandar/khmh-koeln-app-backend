<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Files\Model;

use App\Library\Files\Model\Path;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    public function testDirectoriesFindsTheMatchingFoldersOnly(): void
    {
        $root = sys_get_temp_dir() . '/path-test-' . bin2hex(random_bytes(4));
        mkdir($root . '/src/People/Migrations', recursive: true);
        mkdir($root . '/src/Meetings/Migrations', recursive: true);
        touch($root . '/src/notes.txt');

        $found = Path::of($root)->directories('src/*/Migrations')->map(
            static fn (Path $path): string => $path->toString(),
        );

        self::assertSame([$root . '/src/Meetings/Migrations', $root . '/src/People/Migrations'], $found->toArray());
        self::assertSame([], Path::of($root)->directories('missing/*')->toArray());
    }

    public function testParentAndNameNavigateThePath(): void
    {
        $path = Path::of('/app/src/Context/People/Infrastructure/Persistence/Migrations');

        self::assertSame('People', $path->parent(levels: 3)->name());
        self::assertSame('/app/src/Context/People/Infrastructure/Persistence', $path->parent()->toString());
    }
}
