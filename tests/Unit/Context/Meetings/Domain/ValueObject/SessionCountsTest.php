<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\ValueObject\SessionCounts;
use PHPUnit\Framework\TestCase;

final class SessionCountsTest extends TestCase
{
    public function testHalfTheRosterMarked(): void
    {
        $roster = self::ids(14);

        $counts = SessionCounts::expected($roster, array_slice($roster, offset: 0, length: 7));

        self::assertSame([7, 14, 7, 7, 0], self::numbers($counts));
    }

    public function testAVisitorRaisesOnlyPresentAndNotOnRoster(): void
    {
        $roster = self::ids(14);

        $counts = SessionCounts::expected($roster, [
            ...array_slice($roster, offset: 0, length: 7),
            'visitor',
        ]);

        self::assertSame([8, 14, 7, 7, 1], self::numbers($counts));
    }

    public function testAnOpenRosterHasNoSizeAndNothingUnmarked(): void
    {
        $counts = SessionCounts::open(self::ids(23));

        self::assertSame([23, null, 0, 0, 23], self::numbers($counts));
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
