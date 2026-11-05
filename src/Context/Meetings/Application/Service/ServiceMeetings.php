<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ServiceMeetings
{
    private const int TREND_DAYS = 28;

    private const int NEXT_DAYS = 21;

    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private SessionRepositoryInterface $sessions,
        private PlannedDates $plannedDates,
        private Calendar $calendar,
    ) {
    }

    /**
     * @return ReadableCollection<int, Session>
     */
    public function recentlyCompleted(): ReadableCollection
    {
        $today = $this->calendar->today();
        $schedules = $this->schedules->listed(serviceId: null, stageId: null);

        return $this->sessions
            ->ofSchedulesBetween($schedules, $today->addDays(-self::TREND_DAYS), $today->addDays(-1))
            ->filter(static fn (Session $session): bool => $session->progress()->status === SessionStatus::Completed);
    }

    /**
     * @return ReadableCollection<non-empty-string, PlannedDate>
     */
    public function nextByService(ArchivedStructure $archived): ReadableCollection
    {
        $today = $this->calendar->today();
        $schedules = $this->schedules->listed(serviceId: null, stageId: null);
        $dates = $this->plannedDates->between($schedules, $archived, $today, $today->addDays(self::NEXT_DAYS));
        $upcoming = new ArrayCollection($dates)->filter(static fn (PlannedDate $date): bool => $date->isUpcoming());

        return Collections::firstPerKey($upcoming, keyOf: self::serviceKey(...));
    }

    /**
     * @return non-empty-string
     */
    private static function serviceKey(PlannedDate $date): string
    {
        return $date->schedule->roster()->serviceId->toRfc4122();
    }
}
