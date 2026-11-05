<?php

declare(strict_types=1);

namespace App\Library\Types\Model;

use UnexpectedValueException;

final readonly class ListOf
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public static function instances(mixed $value, string $class): array
    {
        $items = is_array($value) ? $value : throw new UnexpectedValueException('The value is not a list.');

        return array_values(array_map(static fn (mixed $item): object => Type::of($item)->toInstanceOf(
            $class,
        ), $items));
    }
}
