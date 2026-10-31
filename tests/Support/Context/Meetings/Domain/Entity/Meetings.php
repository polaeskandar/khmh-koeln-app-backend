<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Meetings\Domain\Entity;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\Meetings\Domain\ValueObject\DateSpan;
use App\Context\Meetings\Domain\ValueObject\Heading;
use App\Context\Meetings\Domain\ValueObject\MeetingDetails;
use App\Context\Meetings\Domain\ValueObject\Recurrence;
use App\Context\Meetings\Domain\ValueObject\Roster;
use App\Context\Meetings\Domain\ValueObject\TimeSlot;
use App\Context\Meetings\Domain\ValueObject\Timing;
use App\Library\Time\Model\LocalDate;
use App\Library\Time\Model\LocalTime;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class Meetings
{
    public static function roster(): Roster
    {
        return new Roster(
            Uuid::fromString('019a3b6e-0000-7000-8000-000000000001'),
            stageId: null,
            mode: RosterMode::Expected,
        );
    }

    public static function schedule(): Schedule
    {
        return new Schedule(
            self::roster(),
            new MeetingDetails('Sunday class', description: null, location: 'Hall'),
            Recurrence::weekly(1, weekdays: [7], span: new DateSpan(LocalDate::of('2026-09-06'), endsOn: null)),
            new TimeSlot(LocalTime::of('12:00'), LocalTime::of('13:30')),
        );
    }

    public static function session(Schedule $schedule, string $date = '2026-11-08'): Session
    {
        return Session::ofSchedule($schedule, LocalDate::of($date), self::timing($date));
    }

    public static function oneOff(string $date = '2026-11-08'): Session
    {
        return Session::oneOff(
            self::roster(),
            LocalDate::of($date),
            new Heading('Trip', location: null),
            self::timing($date),
        );
    }

    private static function timing(string $date): Timing
    {
        return new Timing(
            new DateTimeImmutable($date . 'T12:00:00+01:00'),
            new DateTimeImmutable($date . 'T13:30:00+01:00'),
        );
    }
}
