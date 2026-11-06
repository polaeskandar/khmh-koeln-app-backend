<?php

declare(strict_types=1);

namespace App\Tests\Support\Platform\OpenApi;

use stdClass;

final readonly class ClosedSchemas
{
    private const array LISTS = ['required', 'enum', 'type', 'oneOf', 'anyOf', 'allOf', 'security', 'tags'];

    /**
     * @param array<array-key, mixed> $node
     *
     * @return array<array-key, mixed>
     */
    public static function of(array $node): array
    {
        $keys = array_keys($node);
        $closed = array_combine($keys, array_map(self::child(...), $node, $keys));

        if (\array_key_exists('properties', $closed) && !\array_key_exists('additionalProperties', $closed)) {
            $closed['additionalProperties'] = false;
        }

        return $closed;
    }

    private static function child(mixed $child, int|string $key): mixed
    {
        if (!\is_array($child)) {
            return $child;
        }

        return $child === [] && !\in_array($key, self::LISTS, strict: true) ? new stdClass() : self::of($child);
    }
}
