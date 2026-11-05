<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Meetings\Domain\Entity;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\Meetings\Domain\ValueObject\DateSpan;
use App\Context\Meetings\Domain\ValueObject\MeetingDetails;
use App\Context\Meetings\Domain\ValueObject\Recurrence;
use App\Context\Meetings\Domain\ValueObject\Roster;
use App\Context\Meetings\Domain\ValueObject\TimeSlot;
use App\Context\Organization\Domain\Entity\Service;
use App\Library\Time\Model\LocalDate;
use App\Library\Time\Model\LocalTime;
use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class Schedules
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function weekly(Service $service, string $name, int $weekday, ?string $endsOn = null): Schedule
    {
        return $this->stored($service, $name, Recurrence::weekly(1, [$weekday], self::span($endsOn)));
    }

    public function monthly(Service $service, string $name, int $monthWeek, int $weekday): Schedule
    {
        return $this->stored($service, $name, Recurrence::monthly($monthWeek, $weekday, self::span(endsOn: null)));
    }

    public function session(Schedule $schedule, string $date, string $set = ''): Session
    {
        $session = new Session($schedule, LocalDate::of($date));
        $database = new Database($this->container);
        $database->persisted($session);

        if ($set !== '') {
            $sql = 'UPDATE meetings.meeting_session SET ' . $set . ' WHERE id = ?';
            $database->connection()->executeStatement($sql, [$session->id()->toRfc4122()]);
            $database->entityManager()->detach($session);
        }

        return $session;
    }

    private function stored(Service $service, string $name, Recurrence $recurrence): Schedule
    {
        $schedule = new Schedule(
            new Roster($service->id(), stageId: null, mode: RosterMode::Expected),
            new MeetingDetails($name, description: null, location: 'Hall'),
            $recurrence,
            new TimeSlot(LocalTime::of('18:30'), LocalTime::of('20:00')),
        );

        new Database($this->container)->persisted($schedule);

        return $schedule;
    }

    private static function span(?string $endsOn): DateSpan
    {
        return new DateSpan(LocalDate::of('2026-09-01'), $endsOn === null ? null : LocalDate::of($endsOn));
    }
}
