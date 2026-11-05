<?php

declare(strict_types=1);

namespace App\Library\Collections\Model;

use Doctrine\Common\Collections\ReadableCollection;

final readonly class Orderings
{
    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param ReadableCollection<TKey, TValue> $items
     * @param callable(TValue): list<string|int> $keysOf
     *
     * @return list<TValue>
     */
    public static function byKeys(ReadableCollection $items, callable $keysOf): array
    {
        $keyed = array_map(static fn (mixed $item): array => [
            'keys' => $keysOf($item),
            'item' => $item,
        ], $items->getValues());

        usort($keyed, static fn (array $one, array $other): int => self::compare($one['keys'], $other['keys']));

        return array_column($keyed, 'item');
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param ReadableCollection<TKey, TValue> $items
     * @param callable(TValue): list<string|int> $keysOf
     *
     * @return list<TValue>
     */
    public static function byKeysDescending(ReadableCollection $items, callable $keysOf): array
    {
        return array_reverse(self::byKeys($items, $keysOf));
    }

    /**
     * @param list<string|int> $one
     * @param list<string|int> $other
     */
    private static function compare(array $one, array $other): int
    {
        foreach ($one as $index => $value) {
            $result = is_string($value)
                ? strcmp($value, (string) ($other[$index] ?? ''))
                : $value <=> ($other[$index] ?? 0);

            if ($result !== 0) {
                return $result;
            }
        }

        return 0;
    }
}
