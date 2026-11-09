<?php

declare(strict_types=1);

namespace App\Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

final readonly class SourceTypes
{
    /**
     * @return list<ReflectionClass<object>>
     */
    public static function all(): array
    {
        return iterator_to_array(self::declared(), preserve_keys: false);
    }

    /**
     * @return list<ReflectionMethod>
     */
    public static function publicMethods(): array
    {
        return array_merge(...array_map(static fn (ReflectionClass $type): array => array_values(array_filter(
            $type->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn (ReflectionMethod $method): bool => $method->class === $type->getName(),
        )), self::all()));
    }

    /**
     * @return iterable<ReflectionClass<object>>
     */
    private static function declared(): iterable
    {
        $src = dirname(__DIR__, levels: 2) . '/src/';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src)) as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains($file->getPathname(), '/Migrations/')) {
                continue;
            }

            $type = 'App\\' . str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen($src)));

            if (class_exists($type) || interface_exists($type) || enum_exists($type)) {
                yield new ReflectionClass($type);
            }
        }
    }
}
