<?php

declare(strict_types=1);

namespace App\Tests\Unit\Library\Time\Client;

use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CalendarMonthsTest extends TestCase
{
    #[DataProvider('months')]
    public function testAMonthLaterIsClampedToTheMonthsEnd(string $from, string $due): void
    {
        $calendar = new Calendar(new MockClock('2026-11-08T12:00:00Z'), 'Europe/Berlin');

        self::assertSame($due, $calendar->monthAfter(LocalDate::of($from))->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function months(): iterable
    {
        yield 'november' => ['2026-11-02', '2026-12-02'];
        yield 'into a new year' => ['2026-12-15', '2027-01-15'];
        yield 'end of january' => ['2027-01-31', '2027-02-28'];
        yield 'leap year' => ['2028-01-31', '2028-02-29'];
    }
}
