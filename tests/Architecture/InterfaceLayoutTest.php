<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class InterfaceLayoutTest extends TestCase
{
    private const array API_FOLDERS = ['Request' => 'Request.php', 'Response' => 'Response.php'];

    private const array CONSOLE_FOLDERS = ['Request' => 'Request.php'];

    private const string BEYOND_ENUMS = '/App\\\\Context\\\\(?!\w+\\\\Domain\\\\Enum\\\\)/';

    public function testAnApiAreaHoldsControllersRequestsAndResponses(): void
    {
        $misplaced = array_filter(
            self::paths(self::root() . '/src/Interface/Api/*/*'),
            static fn (string $path): bool => is_dir($path)
                ? !self::holdsOnly($path, self::API_FOLDERS[basename($path)] ?? null)
                : !str_ends_with($path, 'Controller.php'),
        );

        self::assertSame(
            [],
            array_values($misplaced),
            'src/Interface/Api/<Area> holds *Controller.php, Request/*Request.php and Response/*Response.php only.',
        );
    }

    public function testAConsoleAreaHoldsCommandsAndTheirRequests(): void
    {
        $misplaced = array_filter(
            self::paths(self::root() . '/src/Interface/Console/*/*'),
            static fn (string $path): bool => is_dir($path)
                ? !self::holdsOnly($path, self::CONSOLE_FOLDERS[basename($path)] ?? null)
                : !str_ends_with($path, 'Command.php'),
        );

        self::assertSame(
            [],
            array_values($misplaced),
            'src/Interface/Console/<Area> holds *Command.php and Request/*Request.php only.',
        );
    }

    public function testTheInterfaceReachesTheContextsThroughWorkflowsAndNamesOnlyTheirEnums(): void
    {
        $reaching = array_filter(
            iterator_to_array(self::filesBelow(self::root() . '/src/Interface'), preserve_keys: false),
            static fn (string $path): bool => preg_match(self::BEYOND_ENUMS, (string) file_get_contents($path)) === 1,
        );

        self::assertSame(
            [],
            array_values($reaching),
            'Interface calls workflows; of a context it names only the enums in Domain\Enum.',
        );
    }

    private static function holdsOnly(string $folder, ?string $suffix): bool
    {
        if ($suffix === null) {
            return false;
        }

        $entries = self::paths($folder . '/*');
        $matching = array_filter($entries, static fn (string $path): bool => str_ends_with($path, $suffix));

        return $entries === array_values($matching);
    }

    /**
     * @return iterable<string>
     */
    private static function filesBelow(string $folder): iterable
    {
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder));

        foreach ($entries as $entry) {
            if (!$entry instanceof SplFileInfo || $entry->getExtension() !== 'php') {
                continue;
            }

            yield $entry->getPathname();
        }
    }

    /**
     * @return list<string>
     */
    private static function paths(string $pattern): array
    {
        $paths = glob($pattern);

        return $paths === false ? [] : $paths;
    }

    private static function root(): string
    {
        return dirname(__DIR__, levels: 2);
    }
}
