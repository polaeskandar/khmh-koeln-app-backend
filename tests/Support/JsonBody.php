<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\Assert;

final readonly class JsonBody
{
    /**
     * @return array<array-key, mixed>
     */
    public static function asArray(mixed $value, string $what): array
    {
        Assert::assertIsArray($value, sprintf('%s is not a JSON object or array.', $what));

        return $value;
    }

    /**
     * @param list<string> $keys
     */
    public static function valueAt(mixed $node, array $keys, string $path): mixed
    {
        if ($keys === []) {
            return $node;
        }

        $object = self::asArray($node, sprintf('"%s"', $path));

        if (!array_key_exists($keys[0], $object)) {
            Assert::fail(sprintf('The response has no "%s".', $path));
        }

        return self::valueAt($object[$keys[0]], array_slice($keys, offset: 1), $path);
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    public static function sorted(array $values): array
    {
        ksort($values);

        return $values;
    }
}
