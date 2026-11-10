<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\Meetings\Domain\Repository\AttendanceRepositoryInterface;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\MarkedSessions;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Client\Calendar;
use App\Platform\Access\Model\GroupScope;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ServiceMeetings
{
    private const int TREND_DAYS = 28;

    private const int NEXT_DAYS = 21;

    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private SessionRepositoryInterface $sessions,
        private PlannedDates $plannedDates,
        private AttendanceRepositoryInterface $attendance,
        private Calendar $calendar,
    ) {
    }

    public function recentlyCompleted(GroupScope $scope): MarkedSessions
    {
        $today = $this->calendar->today();
        $schedules = CoveredSchedules::within($scope, $this->schedules->listed(serviceId: null, stageId: null));

        $completed = $this->sessions
            ->ofSchedulesBetween($schedules, $today->addDays(-self::TREND_DAYS), $today->addDays(-1))
            ->filter(static fn (Session $session): bool => $session->progress()->status === SessionStatus::Completed);

        $ids = $completed->map(static fn (Session $session): Uuid => $session->id())->getValues();

        return new MarkedSessions($completed, $schedules, $this->attendance->listPresentBySession($ids));
    }

    /**
     * @return ReadableCollection<non-empty-string, PlannedDate>
     */
    public function nextByService(ArchivedStructure $archived, GroupScope $scope): ReadableCollection
    {
        $today = $this->calendar->today();
        $schedules = CoveredSchedules::within($scope, $this->schedules->listed(serviceId: null, stageId: null));
        $dates = $this->plannedDates->between($schedules, $archived, $today, $today->addDays(self::NEXT_DAYS));
        $upcoming = new ArrayCollection($dates)->filter(static fn (PlannedDate $date): bool => $date->isUpcoming());

        return Collections::firstPerKey($upcoming, keyOf: static fn (PlannedDate $date): string => $date
            ->schedule->roster()
            ->serviceId->toRfc4122());
    }
}
