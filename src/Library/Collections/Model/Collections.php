<?php

declare(strict_types=1);

namespace App\Library\Collections\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class Collections
{
    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param iterable<TKey, TValue> $items
     *
     * @return ArrayCollection<TKey, TValue>
     */
    public static function of(iterable $items): ArrayCollection
    {
        return $items |> iterator_to_array(...) |> self::collected(...);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @template TNewKey of array-key
     *
     * @param ReadableCollection<TKey, TValue> $items
     * @param callable(TValue): TNewKey $keyOf
     *
     * @return ArrayCollection<TNewKey, TValue>
     */
    public static function firstPerKey(ReadableCollection $items, callable $keyOf): ArrayCollection
    {
        $all = $items->toArray();
        $firstKeys = array_map($keyOf, $all) |> array_unique(...);

        return array_combine($firstKeys, array_intersect_key($all, $firstKeys)) |> self::collected(...);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @template TNewKey of array-key
     *
     * @param ReadableCollection<TKey, TValue> $items
     * @param callable(TKey): TNewKey $keyOf
     *
     * @return ArrayCollection<TNewKey, TValue>
     */
    public static function rekeyed(ReadableCollection $items, callable $keyOf): ArrayCollection
    {
        $all = $items->toArray();

        return $all
            |> array_keys(...)
            |> (/** @param list<TKey> $keys */ static fn (array $keys): array => array_map($keyOf, $keys))
            |> (/** @param list<TNewKey> $newKeys */ static fn (array $newKeys): array => array_combine($newKeys, $all))
            |> self::collected(...);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     * @template TResult
     *
     * @param ReadableCollection<TKey, TValue> $items
     * @param callable(TValue): (TResult|null) $map
     *
     * @return ReadableCollection<TKey, TResult>
     */
    public static function mapPresent(ReadableCollection $items, callable $map): ReadableCollection
    {
        return $items->map($map(...))->filter(self::isPresent(...));
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param ReadableCollection<TKey, TValue> $items
     * @param list<TKey> $keys
     *
     * @return ArrayCollection<TKey, TValue>
     */
    public static function withoutKeys(ReadableCollection $items, array $keys): ArrayCollection
    {
        return array_diff_key($items->toArray(), array_flip($keys)) |> self::collected(...);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param ReadableCollection<TKey, TValue> $items
     * @param callable(TValue): string $keyOf
     *
     * @return list<TValue>
     */
    public static function sortedBy(ReadableCollection $items, callable $keyOf): array
    {
        $keyed = array_map(static fn (mixed $item): array => [
            'key' => $keyOf($item),
            'item' => $item,
        ], $items->getValues());

        usort($keyed, static fn (array $one, array $other): int => strcmp($one['key'], $other['key']));

        return array_column($keyed, 'item');
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey, TValue> $items
     *
     * @return ArrayCollection<TKey, TValue>
     */
    private static function collected(array $items): ArrayCollection
    {
        return new ArrayCollection($items);
    }

    private static function isPresent(mixed $value): bool
    {
        return $value !== null;
    }
}
