<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Collections\Model;

use App\Library\Collections\Model\Orderings;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

final class OrderingsTest extends TestCase
{
    public function testEachKeyBreaksTheTiesOfTheOnesBefore(): void
    {
        $rows = new ArrayCollection([['b', 2], ['a', 10], ['a', 9], ['ab', 1]]);

        $ordered = Orderings::byKeys($rows, keysOf: static fn (array $row): array => $row);

        self::assertSame([['a', 9], ['a', 10], ['ab', 1], ['b', 2]], $ordered);
    }
}
