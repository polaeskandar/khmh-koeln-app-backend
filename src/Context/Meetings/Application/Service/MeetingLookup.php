<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\MeetingSlot;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingLookup
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private SessionRepositoryInterface $sessions,
        private PlannedDates $plannedDates,
    ) {
    }

    /**
     * @return list<PlannedDate>
     */
    public function datesOfStage(Uuid $stageId, ArchivedStructure $archived, LocalDate $from, LocalDate $to): array
    {
        $schedules = $this->schedules
            ->listed(serviceId: null, stageId: $stageId)
            ->filter(static fn (Schedule $schedule): bool => $schedule->isActive());

        return $this->plannedDates->between($schedules, $archived, $from, $to);
    }

    public function slotOfSession(Uuid $sessionId): ?MeetingSlot
    {
        $session = $this->sessions->find($sessionId);

        return $session === null ? null : MeetingSlot::ofSession($session);
    }

    public function slotOfSchedule(Uuid $scheduleId, LocalDate $date, ArchivedStructure $archived): ?MeetingSlot
    {
        $schedule = $this->schedules->find($scheduleId);

        return $schedule === null
            ? null
            : new MeetingSlot($schedule, $date, $this->sessions->findOn($schedule, $date), $archived);
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Schedule>
     */
    public function schedules(array $ids): ReadableCollection
    {
        return $this->schedules->findByIds($ids);
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     * @param list<LocalDate> $dates
     *
     * @return ReadableCollection<int, Session>
     */
    public function sessionsOn(ReadableCollection $schedules, array $dates): ReadableCollection
    {
        return $this->sessions->ofSchedulesOn($schedules, $dates);
    }
}
