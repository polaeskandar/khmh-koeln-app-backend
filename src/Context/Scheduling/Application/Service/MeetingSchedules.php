<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Repository\AttendanceRepositoryInterface;
use App\Context\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AgendaDates;
use App\Context\Scheduling\Domain\ValueObject\AgendaRange;
use App\Context\Scheduling\Domain\ValueObject\ArchivedStructure;
use App\Context\Scheduling\Domain\ValueObject\RecurringSchedule;
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
    public function getAgendaOf(
        ReadableCollection $schedules,
        ArchivedStructure $archived,
        AgendaRange $range,
    ): AgendaDates {
        return $this->withPresent($this->plannedDates->getAgendaOf($schedules, $archived, $range));
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    public function getAgendaFor(
        ReadableCollection $schedules,
        ArchivedStructure $archived,
        AgendaRange $range,
    ): AgendaDates {
        return $this->plannedDates->getAgendaOf($schedules, $archived, $range);
    }

    public function getUpcomingOf(ArchivedStructure $archived, AgendaRange $range, GroupScope $scope): AgendaDates
    {
        $schedules = CoveredSchedules::within(
            $scope,
            $archived->inOpenServices($this->schedules->listed(serviceId: null, stageId: null)),
        );

        return $this->withPresent($this->plannedDates->getAgendaOf($schedules, $archived, $range)->withoutCompleted());
    }

    /**
     * @return ReadableCollection<int, Schedule>
     */
    public function listFor(?Uuid $serviceId, ?Uuid $stageId): ReadableCollection
    {
        return $this->schedules->listed($serviceId, $stageId);
    }

    /**
     * @return ReadableCollection<int, Schedule>
     */
    public function listRecurring(?Uuid $serviceId, ?Uuid $stageId): ReadableCollection
    {
        return $this->listFor($serviceId, $stageId)->filter(RecurringSchedule::is(...));
    }

    /**
     * @return ReadableCollection<int, Schedule>
     */
    public function chosen(?Uuid $scheduleId, ?Uuid $serviceId, ?Uuid $stageId): ReadableCollection
    {
        return $scheduleId === null ? $this->listFor($serviceId, $stageId) : $this->schedules->findByIds([$scheduleId]);
    }

    public function get(Uuid $id): Schedule
    {
        return RecurringSchedule::required($this->schedules->find($id));
    }

    private function withPresent(AgendaDates $dates): AgendaDates
    {
        $opened = $dates
            ->opened()
            ->map(static fn (Session $session): Uuid => $session->id())
            ->getValues();

        return $dates->withPresent($this->attendance->listPresentBySession($opened));
    }
}
