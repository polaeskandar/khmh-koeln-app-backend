<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSchedules;
use App\Context\Meetings\Domain\ValueObject\AgendaDates;
use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Library\Time\Client\Calendar;
use App\Workflow\Attendance\Model\SessionTallies;
use App\Workflow\Meetings\Model\Agenda;
use App\Workflow\Meetings\Model\AgendaFilter;
use App\Workflow\Meetings\Model\AgendaViews;
use App\Workflow\Meetings\Model\RosterReferences;
use App\Workflow\Meetings\Model\SessionAbilities;

final readonly class ShowAgendaWorkflow
{
    // @mago-expect lint:excessive-parameter-list
    public function __construct(
        private AccessCheck $access,
        private MeetingSchedules $schedules,
        private StructureLookup $structure,
        private Attendees $attendees,
        private AttendanceMarks $marks,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(AgendaFilter $filter): Agenda
    {
        $this->access->require(Action::Read, Resource::Services);

        $range = AgendaRange::of($filter->from, $filter->to, $this->calendar->today());
        $archived = ArchivedStructure::of($this->structure->archivedIds());

        $dates = $this->schedules->agendaOf(
            $filter->scheduleId,
            $filter->serviceId,
            $filter->stageId,
            $archived,
            $range,
        );

        return Agenda::of($range, $dates, $this->viewsOf($dates));
    }

    private function viewsOf(AgendaDates $dates): AgendaViews
    {
        return new AgendaViews(
            RosterReferences::lookedUpIn($this->structure, $dates->schedules),
            $this->calendar,
            SessionAbilities::grantedBy($this->access),
            SessionTallies::ofOpened($this->attendees, $this->marks, $dates->sessions),
        );
    }
}
