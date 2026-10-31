<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TestLayoutTest extends TestCase
{
    private const array SUITES = ['Unit', 'Functional'];

    private const array MIRRORED_HELPERS = ['Support', 'Fixtures'];

    #[DataProvider('suiteFiles')]
    public function testEveryTestSitsAtThePathOfTheClassItCovers(string $suite, string $relative): void
    {
        $path = $suite . '/' . $relative;

        self::assertStringEndsWith(
            'Test.php',
            $path,
            sprintf('%s is not a test; helpers belong in tests/Support.', $path),
        );

        $subjectFolder = self::subjectFolder(dirname($relative));
        self::assertDirectoryExists($subjectFolder, sprintf('%s mirrors no folder.', $path));

        $testedName = basename($path, suffix: 'Test.php');

        $covered = array_filter(self::classNamesIn($subjectFolder), static fn (string $class): bool => str_starts_with(
            $testedName,
            $class,
        ));

        self::assertNotEmpty($covered, sprintf('The name of %s starts with no class of its folder.', $path));
    }

    public function testSupportAndFixtureFoldersMirrorSrc(): void
    {
        $unmirrored = array_values(array_filter(
            self::helperFolders(),
            static fn (string $folder): bool => !is_dir(self::root() . '/src/' . $folder),
        ));

        self::assertSame([], $unmirrored, 'tests/Support and tests/Fixtures folders must mirror src.');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function suiteFiles(): iterable
    {
        foreach (self::SUITES as $suite) {
            foreach (self::entriesBelow('tests/' . $suite) as $relative => $entry) {
                if (!$entry->isFile()) {
                    continue;
                }

                yield $suite . '/' . $relative => [$suite, $relative];
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function helperFolders(): array
    {
        return array_merge(...array_map(self::foldersBelow(...), self::MIRRORED_HELPERS));
    }

    /**
     * @return list<string>
     */
    private static function foldersBelow(string $helper): array
    {
        $entries = iterator_to_array(self::entriesBelow('tests/' . $helper));

        return array_keys(array_filter($entries, static fn (SplFileInfo $entry): bool => $entry->isDir()));
    }

    private static function subjectFolder(string $folder): string
    {
        $topFolder = explode('/', $folder, limit: 2)[0];

        return match (true) {
            in_array($topFolder, self::MIRRORED_HELPERS, strict: true) => self::root() . '/tests/' . $folder,
            $topFolder === 'Tools' => self::root() . '/tools/' . substr($folder, offset: 6),
            default => self::root() . '/src/' . $folder,
        };
    }

    /**
     * @return iterable<string, SplFileInfo>
     */
    private static function entriesBelow(string $path): iterable
    {
        $root = self::root() . '/' . $path;

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($entries as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }

            yield substr($entry->getPathname(), strlen($root) + 1) => $entry;
        }
    }

    /**
     * @return list<string>
     */
    private static function classNamesIn(string $folder): array
    {
        $files = glob($folder . '/*.php');

        return array_map(
            static fn (string $file): string => basename($file, suffix: '.php'),
            $files === false ? [] : $files,
        );
    }

    private static function root(): string
    {
        return dirname(__DIR__, levels: 2);
    }
}
