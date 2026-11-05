<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\DateSpan;
use App\Context\Meetings\Domain\ValueObject\MeetingDetails;
use App\Context\Meetings\Domain\ValueObject\Recurrence;
use App\Context\Meetings\Domain\ValueObject\Roster;
use App\Context\Meetings\Domain\ValueObject\TimeSlot;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalTime;
use Symfony\Component\Uid\Uuid;

final readonly class DemoSchedules
{
    private const int RUNNING_SINCE_DAYS = 56;

    private const array SUNDAY_CLASS = ['Sunday class', [7], ['12:00', '13:15']];

    private const array MEETINGS = [
        'Youth Meeting' => [['Youth meeting', [5], ['19:00', '21:00']]],
        '3rd Class' => [self::SUNDAY_CLASS, ['3rd Class practice', [1, 2, 3, 4, 5, 6], ['17:00', '18:00']]],
    ];

    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private SessionGenerator $generator,
        private Calendar $calendar,
    ) {
    }

    /**
     * @return list<Schedule> the group's schedules, its Sunday class first
     */
    public function seed(string $group, string $serviceId, ?string $stageId): array
    {
        $stage = $stageId === null ? null : Uuid::fromString($stageId);
        $roster = new Roster(Uuid::fromString($serviceId), $stage, RosterMode::Expected);
        $today = $this->calendar->today();
        $span = new DateSpan($today->addDays(-self::RUNNING_SINCE_DAYS), endsOn: null);

        $seeded = [];

        foreach (self::MEETINGS[$group] ?? [self::SUNDAY_CLASS] as [$name, $weekdays, [$startTime, $endTime]]) {
            $schedule = new Schedule(
                $roster,
                new MeetingDetails($name, description: null, location: 'Church hall'),
                Recurrence::weekly(1, $weekdays, $span),
                new TimeSlot(LocalTime::of($startTime), LocalTime::of($endTime)),
            );

            $this->schedules->save($schedule);
            $this->generator->generateFor($schedule, $today, ArchivedStructure::of([]));
            $seeded[] = $schedule;
        }

        return $seeded;
    }
}
