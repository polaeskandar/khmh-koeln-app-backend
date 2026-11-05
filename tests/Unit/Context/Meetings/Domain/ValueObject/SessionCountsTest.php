<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\ValueObject\ErasedCounts;
use App\Context\Meetings\Domain\ValueObject\SessionCounts;
use PHPUnit\Framework\TestCase;

final class SessionCountsTest extends TestCase
{
    public function testHalfTheRosterMarked(): void
    {
        $roster = self::ids(14);

        $counts = SessionCounts::expected($roster, array_slice($roster, offset: 0, length: 7), new ErasedCounts());

        self::assertSame([7, 14, 7, 7, 0], self::numbers($counts));
    }

    public function testAVisitorRaisesOnlyPresentAndNotOnRoster(): void
    {
        $roster = self::ids(14);

        $counts = SessionCounts::expected(
            $roster,
            [
                ...array_slice($roster, offset: 0, length: 7),
                'visitor',
            ],
            new ErasedCounts(),
        );

        self::assertSame([8, 14, 7, 7, 1], self::numbers($counts));
    }

    public function testAnOpenRosterHasNoSizeAndNothingUnmarked(): void
    {
        $counts = SessionCounts::open(self::ids(23), new ErasedCounts());

        self::assertSame([23, null, 0, 0, 23], self::numbers($counts));
    }

    public function testErasedPeopleKeepTheirPlaceInTheTotals(): void
    {
        $roster = self::ids(14);

        $counts = SessionCounts::expected(
            $roster,
            array_slice($roster, offset: 0, length: 7),
            new ErasedCounts(roster: 2, present: 3, rosterPresent: 1),
        );

        self::assertSame([10, 16, 8, 8, 2], self::numbers($counts));
    }

    public function testErasedVisitorsOfAnOpenRosterCountAsNotOnTheRoster(): void
    {
        $counts = SessionCounts::open([], new ErasedCounts(present: 2));

        self::assertSame([2, null, 0, 0, 2], self::numbers($counts));
    }

    /**
     * @return list<string>
     */
    private static function ids(int $count): array
    {
        return array_map(static fn (int $number): string => 'person-' . $number, range(start: 1, end: $count));
    }

    /**
     * @return list<int|null>
     */
    private static function numbers(SessionCounts $counts): array
    {
        return [
            $counts->present,
            $counts->rosterSize,
            $counts->rosterPresent,
            $counts->notMarked,
            $counts->notOnRoster,
        ];
    }
}
