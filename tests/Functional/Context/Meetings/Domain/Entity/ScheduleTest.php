<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Meetings\Domain\Entity;

use App\Tests\Support\Context\Meetings\Domain\Entity\Meetings;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ScheduleTest extends KernelTestCase
{
    public function testAScheduleIsStoredWithItsRecurrence(): void
    {
        $this->database()->persisted(Meetings::schedule());

        self::assertSame(
            [
                'frequency' => 'weekly',
                'interval' => 1,
                'weekdays' => 64,
                'month_week' => null,
                'start_time' => '12:00:00',
            ],
            $this
                ->database()
                ->connection()
                ->fetchAssociative(
                    'SELECT frequency, "interval", weekdays, month_week, start_time FROM meetings.meeting_schedule',
                ),
        );
    }

    #[DataProvider('violations')]
    public function testEachCheckRefusesItsRow(string $set): void
    {
        $this->database()->persisted(Meetings::schedule());

        $this->database()->assertRefused(Database::CHECK_VIOLATION, 'UPDATE meetings.meeting_schedule SET ' . $set);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function violations(): iterable
    {
        yield 'blank name' => ["name = ' '"];
        yield 'unknown roster mode' => ["roster_mode = 'closed'"];
        yield 'end before start' => ["end_time = '11:00'"];
        yield 'end at start' => ["end_time = '12:00'"];
        yield 'interval 0' => ['"interval" = 0'];
        yield 'interval 53' => ['"interval" = 53'];
        yield 'no weekdays' => ['weekdays = 0'];
        yield 'weekdays beyond Sunday' => ['weekdays = 128'];
        yield 'unknown frequency' => ["frequency = 'daily'"];
        yield 'weekly with a month week' => ['month_week = 1'];
        yield 'monthly without a month week' => ["frequency = 'monthly'"];
        yield 'monthly on two weekdays' => ["frequency = 'monthly', month_week = 1, weekdays = 65"];
        yield 'fifth week' => ["frequency = 'monthly', month_week = 5"];
        yield 'ends before it starts' => ["ends_on = '2026-09-05'"];
    }

    public function testAMonthlyScheduleOnTheLastWeekdayIsAccepted(): void
    {
        $this->database()->persisted(Meetings::schedule());

        self::assertSame(
            1,
            $this
                ->database()
                ->connection()
                ->executeStatement("UPDATE meetings.meeting_schedule SET frequency = 'monthly', month_week = -1"),
        );
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
