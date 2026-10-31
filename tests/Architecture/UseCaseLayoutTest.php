<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class UseCaseLayoutTest extends TestCase
{
    private const array REQUEST_SUFFIXES = ['Command' => 'Command', 'Query' => 'Query'];

    public function testEveryUseCaseFolderHoldsItsRequestAndItsHandlerOnly(): void
    {
        $misplaced = [];
        foreach (self::REQUEST_SUFFIXES as $kind => $suffix) {
            foreach (self::useCaseFolders($kind) as $folder) {
                $name = basename($folder);
                $expected = [$name . $suffix . '.php', $name . 'Handler.php'];
                $files = array_map(basename(...), self::paths($folder . '/*'));
                sort($expected);
                sort($files);
                if ($files !== $expected) {
                    $misplaced[] = sprintf('%s holds %s', $folder, implode(', ', $files));
                }
            }
        }

        self::assertSame(
            [],
            $misplaced,
            'A use case folder holds exactly <Name>Command|<Name>Query and <Name>Handler.',
        );
    }

    public function testUseCasesAreEitherCommandsOrQueries(): void
    {
        $folders = self::paths(self::root() . '/src/Interface/Application/*/UseCase/*');
        $kinds = array_unique(array_map(basename(...), $folders));

        self::assertSame([], array_values(array_diff($kinds, array_keys(self::REQUEST_SUFFIXES))));
    }

    /**
     * @return list<string>
     */
    private static function useCaseFolders(string $kind): array
    {
        return self::paths(self::root() . '/src/Interface/Application/*/UseCase/' . $kind . '/*');
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
