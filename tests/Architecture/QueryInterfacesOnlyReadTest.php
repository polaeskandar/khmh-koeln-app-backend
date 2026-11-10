<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class QueryInterfacesOnlyReadTest extends TestCase
{
    private const string QUERY_INTERFACES = '/src/Context/*/Domain/Query/*.php';

    private const string QUERY_METHOD = '/::(find|get|list|count|exists|is|has)([A-Z]\w*)?$/';

    public function testAQueryInterfaceOffersOnlyReads(): void
    {
        $writes = array_filter(
            iterator_to_array(self::queryMethods(), preserve_keys: false),
            static fn (string $method): bool => preg_match(self::QUERY_METHOD, $method) !== 1,
        );

        self::assertSame(
            [],
            array_values($writes),
            'A query interface in Domain/Query offers only reads: find, get, list, count, exists, is, has.',
        );
    }

    /**
     * @return iterable<string>
     */
    private static function queryMethods(): iterable
    {
        foreach (self::paths() as $path) {
            $context = basename(dirname($path, levels: 3));
            $interface = 'App\\Context\\' . $context . '\\Domain\\Query\\' . basename($path, suffix: '.php');

            if (!interface_exists($interface)) {
                yield $interface;

                continue;
            }

            foreach (new ReflectionClass($interface)->getMethods() as $method) {
                yield $interface . '::' . $method->getName();
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function paths(): array
    {
        $paths = glob(dirname(__DIR__, levels: 2) . self::QUERY_INTERFACES);

        return $paths === false ? [] : $paths;
    }
}
