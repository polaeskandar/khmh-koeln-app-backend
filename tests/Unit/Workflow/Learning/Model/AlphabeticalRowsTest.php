<?php

declare(strict_types=1);

namespace App\Tests\Unit\Workflow\Learning\Model;

use App\Workflow\Learning\Model\AlphabeticalRows;
use App\Workflow\Learning\Model\AudienceRow;
use App\Workflow\People\Model\PersonReference;
use PHPUnit\Framework\TestCase;

final class AlphabeticalRowsTest extends TestCase
{
    public function testTheLastNameDecidesBeforeTheFirstName(): void
    {
        $rows = AlphabeticalRows::of([
            self::row('3', 'Anna', 'Hahne'),
            self::row('2', 'Zoe', 'Hahn'),
            self::row('1', 'Émile', 'Ägypter'),
            self::row('4', 'Adam', 'Hahn'),
        ]);

        self::assertSame(
            ['Émile Ägypter', 'Adam Hahn', 'Zoe Hahn', 'Anna Hahne'],
            array_map(
                static fn (AudienceRow $row): string => $row->person->firstName . ' ' . $row->person->lastName,
                $rows,
            ),
        );
    }

    private static function row(string $id, string $firstName, string $lastName): AudienceRow
    {
        return new AudienceRow(
            new PersonReference($id, $firstName, $lastName, displayName: null),
            inStage: true,
            report: null,
            parentAccounts: [],
        );
    }
}
