<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\AttendanceRepositoryInterface;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\AgendaDates;
use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\RecurringSchedule;
use App\Library\Problem\Exception\InvalidInput;
use App\Platform\Access\Model\GroupScope;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingSchedules
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private PlannedDates $plannedDates,
        private AttendanceRepositoryInterface $attendance,
    ) {
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    public function agendaOf(
        ReadableCollection $schedules,
        ArchivedStructure $archived,
        AgendaRange $range,
    ): AgendaDates {
        return $this->withPresent($this->plannedDates->agendaOf($schedules, $archived, $range));
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    public function agendaFor(
        ReadableCollection $schedules,
        ArchivedStructure $archived,
        AgendaRange $range,
    ): AgendaDates {
        return $this->plannedDates->agendaOf($schedules, $archived, $range);
    }

    public function upcomingOf(ArchivedStructure $archived, AgendaRange $range, GroupScope $scope): AgendaDates
    {
        $schedules = CoveredSchedules::within(
            $scope,
            $archived->inOpenServices($this->schedules->listed(serviceId: null, stageId: null)),
        );

        return $this->withPresent($this->plannedDates->agendaOf($schedules, $archived, $range)->withoutCompleted());
    }

    /**
     * @return ReadableCollection<int, Schedule>
     */
    public function listed(?string $serviceId, ?string $stageId): ReadableCollection
    {
        return $this->schedules->listed(self::idOf('serviceId', $serviceId), self::idOf('stageId', $stageId));
    }

    /**
     * @return ReadableCollection<int, Schedule>
     */
    public function recurring(?string $serviceId, ?string $stageId): ReadableCollection
    {
        return $this->listed($serviceId, $stageId)->filter(RecurringSchedule::is(...));
    }

    /**
     * @return ReadableCollection<int, Schedule>
     */
    public function chosen(?string $scheduleId, ?string $serviceId, ?string $stageId): ReadableCollection
    {
        $id = self::idOf('scheduleId', $scheduleId);

        return $id === null ? $this->listed($serviceId, $stageId) : $this->schedules->findByIds([$id]);
    }

    public function find(string $id): Schedule
    {
        $schedule = Uuid::isValid($id) ? $this->schedules->find(Uuid::fromString($id)) : null;

        return RecurringSchedule::required($schedule);
    }

    private function withPresent(AgendaDates $dates): AgendaDates
    {
        $opened = $dates
            ->opened()
            ->map(static fn (Session $session): Uuid => $session->id())
            ->getValues();

        return $dates->withPresent($this->attendance->presentBySession($opened));
    }

    private static function idOf(string $field, ?string $value): ?Uuid
    {
        if ($value === null) {
            return null;
        }

        return Uuid::isValid($value)
            ? Uuid::fromString($value)
            : throw InvalidInput::field($field, 'validation.invalidType');
    }
}
