<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Context\Scheduling\Application\Service\CoveredSchedules;
use App\Context\Scheduling\Application\Service\MeetingSchedules;
use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\ValueObject\AgendaDates;
use App\Context\Scheduling\Domain\ValueObject\AgendaRange;
use App\Context\Scheduling\Domain\ValueObject\ArchivedStructure;
use App\Context\Scheduling\Domain\ValueObject\PlannedDate;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Platform\Access\Model\GroupScope;
use App\Workflow\Scheduling\Model\Agenda;
use App\Workflow\Scheduling\Model\AgendaCounts;
use App\Workflow\Scheduling\Model\AgendaFilter;
use App\Workflow\Scheduling\Model\AgendaItem;
use App\Workflow\Scheduling\Model\PlannedSession;
use App\Workflow\Scheduling\Model\RosterReferences;
use App\Workflow\Scheduling\Model\SessionTallies;
use App\Workflow\Scheduling\Model\SessionTally;
use App\Workflow\Scheduling\Model\SessionTimes;
use App\Workflow\Scheduling\Model\SessionView;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ShowAgendaWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSchedules $schedules,
        private StructureLookup $structure,
        private Calendar $calendar,
        private Attendees $attendees,
    ) {
    }

    public function __invoke(AgendaFilter $filter): Agenda
    {
        $scope = $this->access->requireScope(Action::Read, Resource::Services);

        $today = $this->calendar->today();
        $archived = ArchivedStructure::of($this->structure->archivedIds());
        $days = $filter->daysAhead;
        $choice = $filter->choice;

        $range = $days === null
            ? AgendaRange::of($filter->from, $filter->to, $today)
            : AgendaRange::ahead($today, $days);

        $dates = $days === null
            ? $this->schedules->getAgendaOf(
                CoveredSchedules::within($scope, $this->schedules->chosen(
                    Ids::toUuidOrNull($choice->scheduleId),
                    Ids::toUuidOrNull($choice->serviceId),
                    Ids::toUuidOrNull($choice->stageId),
                )),
                $archived,
                $range,
            )
            : $this->schedules->getUpcomingOf($archived, $range, $scope);

        return Agenda::of($range, $this->itemsOf($dates, $this->referencesOf($dates->schedules)));
    }

    /**
     * @return list<AgendaItem>
     */
    private function itemsOf(AgendaDates $dates, RosterReferences $references): array
    {
        $attendance = $this->access->getScope(Action::Read, Resource::Attendance);
        $tallies = $this->talliesOf($dates);

        $sessionOf = fn (Session $session): AgendaItem => AgendaItem::session(
            self::shownTo($attendance, $session, $this->viewOf($session, $references)),
            AgendaCounts::of($tallies, $session),
        );

        $plannedOf = fn (PlannedDate $date): AgendaItem => AgendaItem::planned(PlannedSession::of(
            $date,
            $references,
            $this->timesOf($date->date, $date->schedule->timeSlot()),
        ));

        return [
            ...$dates->sessions->map($sessionOf)->getValues(),
            ...new ArrayCollection($dates->planned)->map($plannedOf)->getValues(),
        ];
    }

    private static function shownTo(GroupScope $attendance, Session $session, SessionView $view): SessionView
    {
        return $attendance->covers(CoveredSchedules::groupOfSession($session)) ? $view : $view->withoutCancelReason();
    }

    /**
     * @return ReadableCollection<non-empty-string, SessionTally>
     */
    private function talliesOf(AgendaDates $dates): ReadableCollection
    {
        $opened = $dates->opened();
        $rosters = $this->attendees->listRostersOn(SessionTallies::rosterKeysOf($opened, $dates->schedules));

        return SessionTallies::of($rosters, $dates->present, $opened, $dates->schedules);
    }

    private function viewOf(Session $session, RosterReferences $references): SessionView
    {
        $times = $this->timesOf($session->occurrenceDate(), $session->plan()->timeSlot);

        return SessionView::of($session, $references, $times->startsAt, $times->endsAt);
    }

    private function timesOf(LocalDate $date, TimeSlot $slot): SessionTimes
    {
        $endTime = $slot->endTime;
        $endsAt = $endTime === null ? null : $this->calendar->instantAt($date, $endTime);

        return SessionTimes::ofSlot($date, $slot, $this->calendar->instantAt($date, $slot->startTime), $endsAt);
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    private function referencesOf(ReadableCollection $schedules): RosterReferences
    {
        return RosterReferences::of(
            $this->structure->listServices(RosterReferences::serviceIdsOf($schedules)),
            $this->structure->listStages(RosterReferences::stageIdsOf($schedules)),
        );
    }
}
