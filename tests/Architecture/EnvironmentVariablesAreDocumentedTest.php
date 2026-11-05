<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class EnvironmentVariablesAreDocumentedTest extends TestCase
{
    private const array IGNORED = ['config/reference.php'];

    public function testEveryVariableTheCodeReadsIsInTheConfigurationTable(): void
    {
        $documented = (string) file_get_contents(self::root() . '/docs/operations.md');

        foreach (self::variables() as $variable) {
            self::assertStringContainsString(
                '| `' . $variable . '` |',
                $documented,
                sprintf('%s is read by the code but missing from docs/operations.md § Configuration.', $variable),
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function variables(): array
    {
        $found = [];

        foreach (self::files() as $path => $content) {
            $pattern = str_starts_with($path, 'src/')
                ? "/Autowire\\(env: '(?:[a-z]+:)*([A-Z0-9_]+)'/"
                : '/%env\((?:[a-z]+:)*:?([A-Z0-9_]+)\)%/';

            $matches = [];
            preg_match_all($pattern, $content, $matches);
            $found[] = $matches[1] ?? [];
        }

        $names = array_merge([], ...$found);

        $names = array_values(array_unique(array_filter(
            $names,
            static fn (string $name): bool => !str_starts_with($name, 'SYMFONY_'),
        )));

        sort($names);

        return $names;
    }

    /**
     * @return iterable<string, string>
     */
    private static function files(): iterable
    {
        foreach (['config', 'src'] as $folder) {
            $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::root() . '/' . $folder));

            foreach ($entries as $entry) {
                if (!$entry instanceof SplFileInfo || !$entry->isFile()) {
                    continue;
                }

                $relative = substr($entry->getPathname(), strlen(self::root()) + 1);

                if (!in_array($relative, self::IGNORED, strict: true)) {
                    yield $relative => (string) file_get_contents($entry->getPathname());
                }
            }
        }
    }

    private static function root(): string
    {
        return dirname(__DIR__, levels: 2);
    }
}
