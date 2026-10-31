<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class UseCaseLayoutTest extends TestCase
{
    private const array KINDS = ['Command', 'Query'];

    public function testEveryUseCaseFolderHoldsItsRequestAndItsHandlerOnly(): void
    {
        $misplaced = array_filter(array_map(
            self::misplacement(...),
            self::paths(self::root() . '/src/Interface/Application/*/UseCase/*/*'),
        ));

        self::assertSame(
            [],
            array_values($misplaced),
            'A use case folder holds exactly <Name>Command|<Name>Query and <Name>Handler.',
        );
    }

    public function testUseCasesAreEitherCommandsOrQueries(): void
    {
        $kinds = array_unique(array_map(
            basename(...),
            self::paths(self::root() . '/src/Interface/Application/*/UseCase/*'),
        ));

        self::assertSame([], array_values(array_diff($kinds, self::KINDS)));
    }

    private static function misplacement(string $folder): ?string
    {
        $name = basename($folder);
        $expected = [$name . 'Handler.php', $name . basename(dirname($folder)) . '.php'];
        $files = array_map(basename(...), self::paths($folder . '/*'));
        sort($expected);
        sort($files);

        return $files === $expected ? null : sprintf('%s holds %s', $folder, implode(', ', $files));
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
