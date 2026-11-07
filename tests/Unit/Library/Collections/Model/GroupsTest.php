<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Collections\Model;

use App\Library\Collections\Model\Groups;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

final class GroupsTest extends TestCase
{
    public function testItemsAreGroupedInTheirOrder(): void
    {
        $rows = new ArrayCollection([
            ['key' => 'a', 'value' => 1],
            ['key' => 'b', 'value' => 2],
            ['key' => 'a', 'value' => 3],
        ]);

        $groups = Groups::of(
            $rows,
            keyOf: static fn (array $row): string => $row['key'],
            valueOf: static fn (array $row): int => $row['value'],
        );

        self::assertSame(['a' => [1, 3], 'b' => [2]], $groups->toArray());
    }
}
