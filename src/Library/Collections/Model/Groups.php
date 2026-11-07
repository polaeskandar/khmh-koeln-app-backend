<?php

declare(strict_types=1);

namespace App\Library\Collections\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class Groups
{
    /**
     * @template TKey of array-key
     * @template TValue
     * @template TGroupValue
     *
     * @param ReadableCollection<TKey, TValue> $items
     * @param callable(TValue): string $keyOf
     * @param callable(TValue): TGroupValue $valueOf
     *
     * @return ArrayCollection<string, non-empty-list<TGroupValue>>
     */
    public static function of(ReadableCollection $items, callable $keyOf, callable $valueOf): ArrayCollection
    {
        $groups = [];

        foreach ($items as $item) {
            $groups[$keyOf($item)][] = $valueOf($item);
        }

        return new ArrayCollection($groups);
    }
}
