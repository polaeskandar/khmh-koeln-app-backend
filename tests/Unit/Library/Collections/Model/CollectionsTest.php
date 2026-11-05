<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Collections\Model;

use App\Library\Collections\Model\Collections;
use ArrayIterator;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

final class CollectionsTest extends TestCase
{
    public function testOfKeepsTheKeysOfAnIterable(): void
    {
        self::assertSame(['a' => 1, 'b' => 2], Collections::of(new ArrayIterator(['a' => 1, 'b' => 2]))->toArray());
    }

    public function testFirstPerKeyKeepsTheFirstItemOfEachKey(): void
    {
        $words = new ArrayCollection(['apple', 'avocado', 'banana']);

        self::assertSame(
            ['a' => 'apple', 'b' => 'banana'],
            Collections::firstPerKey($words, static fn (string $word): string => $word[0])->toArray(),
        );
    }

    public function testRekeyedRenamesEveryKey(): void
    {
        $values = new ArrayCollection(['{{ max }}' => 120]);

        self::assertSame(
            ['max' => 120],
            Collections::rekeyed($values, static fn (string $key): string => trim($key, characters: '{} '))->toArray(),
        );
    }

    public function testMapPresentDropsTheItemsMappedToNull(): void
    {
        $values = new ArrayCollection(['a' => '1', 'b' => 'x', 'c' => '3']);

        self::assertSame(
            ['a' => 1, 'c' => 3],
            Collections::mapPresent($values, static fn (string $value): ?int => ctype_digit($value)
                ? (int) $value
                : null)->toArray(),
        );
    }

    public function testWithoutKeysAndOnlyKeysSplitByTheGivenKeys(): void
    {
        $values = new ArrayCollection(['limit' => 120, 'value' => 'Kirollos', 'hint' => 'x']);

        self::assertSame(['limit' => 120], Collections::withoutKeys($values, ['value', 'hint'])->toArray());

        self::assertSame(
            ['value' => 'Kirollos', 'hint' => 'x'],
            Collections::onlyKeys($values, ['hint', 'value'])->toArray(),
        );
    }

    public function testMapPresentKeepsFalseyResultsAndKeys(): void
    {
        $values = new ArrayCollection(['zero' => 0, 'empty' => '', 'no' => false, 'none' => null]);

        self::assertSame(
            ['zero' => 0, 'empty' => '', 'no' => false],
            Collections::mapPresent(
                $values,
                static fn (int|string|bool|null $value): int|string|bool|null => $value,
            )->toArray(),
        );
    }

    public function testSortedByOrdersByTheKeyAndReindexes(): void
    {
        $sorted = Collections::sortedBy(
            new ArrayCollection(['x' => 'b2', 'y' => 'a9', 'z' => 'b1']),
            keyOf: static fn (string $item): string => $item,
        );

        self::assertSame(['a9', 'b1', 'b2'], $sorted);
    }
}
