<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSchedules;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\AgendaDates;
use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Context\Meetings\Domain\ValueObject\TimeSlot;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Workflow\Attendance\Model\SessionTallies;
use App\Workflow\Attendance\Model\SessionTally;
use App\Workflow\Meetings\Model\Agenda;
use App\Workflow\Meetings\Model\AgendaCounts;
use App\Workflow\Meetings\Model\AgendaFilter;
use App\Workflow\Meetings\Model\AgendaItem;
use App\Workflow\Meetings\Model\PlannedSession;
use App\Workflow\Meetings\Model\RosterReferences;
use App\Workflow\Meetings\Model\SessionTimes;
use App\Workflow\Meetings\Model\SessionView;
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
        $this->access->require(Action::Read, Resource::Services);

        $today = $this->calendar->today();
        $archived = ArchivedStructure::of($this->structure->archivedIds());
        $days = $filter->daysAhead;
        $choice = $filter->choice;

        $range = $days === null
            ? AgendaRange::of($filter->from, $filter->to, $today)
            : AgendaRange::ahead($today, $days);

        $dates = $days === null
            ? $this->schedules->agendaOf($choice->scheduleId, $choice->serviceId, $choice->stageId, $archived, $range)
            : $this->schedules->upcomingOf($archived, $range);

        return Agenda::of($range, $this->itemsOf($dates, $this->referencesOf($dates->schedules)));
    }

    /**
     * @return list<AgendaItem>
     */
    private function itemsOf(AgendaDates $dates, RosterReferences $references): array
    {
        $shown = $this->access->allows(Action::Read, Resource::Attendance)
            ? static fn (SessionView $view): SessionView => $view
            : static fn (SessionView $view): SessionView => $view->withoutCancelReason();

        $tallies = $this->talliesOf($dates);

        $sessionOf = fn (Session $session): AgendaItem => AgendaItem::session(
            $shown($this->viewOf($session, $references)),
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

    /**
     * @return ReadableCollection<non-empty-string, SessionTally>
     */
    private function talliesOf(AgendaDates $dates): ReadableCollection
    {
        $opened = $dates->opened();
        $rosters = $this->attendees->rostersOn(SessionTallies::rosterKeysOf($opened, $dates->schedules));

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
            $this->structure->services(RosterReferences::serviceIdsOf($schedules)),
            $this->structure->stages(RosterReferences::stageIdsOf($schedules)),
        );
    }
}
