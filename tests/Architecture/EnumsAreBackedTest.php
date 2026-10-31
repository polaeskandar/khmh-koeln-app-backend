<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionEnum;
use SplFileInfo;

final class EnumsAreBackedTest extends TestCase
{
    public function testEveryEnumInSrcHasABackingType(): void
    {
        $pure = array_values(array_filter(
            iterator_to_array(self::enumsInSrc(), preserve_keys: false),
            static fn (ReflectionEnum $enum): bool => !$enum->isBacked(),
        ));

        self::assertSame([], array_map(static fn (ReflectionEnum $enum): string => $enum->getName(), $pure));
    }

    /**
     * @return iterable<ReflectionEnum>
     */
    private static function enumsInSrc(): iterable
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, levels: 2) . '/src'));

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $enum = self::declaredEnum((string) file_get_contents($file->getPathname()));

            if ($enum !== null && enum_exists($enum)) {
                yield new ReflectionEnum($enum);
            }
        }
    }

    private static function declaredEnum(string $source): ?string
    {
        $match = [];

        if (preg_match('/^namespace ([^;]+);.*?^enum (\w+)/ms', $source, $match) !== 1) {
            return null;
        }

        return ($match[1] ?? '') . '\\' . ($match[2] ?? '');
    }
}
