<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSchedules;
use App\Context\Meetings\Application\Service\PlannedDates;
use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Time\Client\Calendar;
use App\Workflow\Meetings\Model\Agenda;
use App\Workflow\Meetings\Model\AgendaFilter;
use App\Workflow\Meetings\Model\RosterReferences;
use App\Workflow\Meetings\Model\SessionAbilities;

final readonly class ShowAgendaWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSchedules $schedules,
        private PlannedDates $plannedDates,
        private StructureLookup $structure,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(AgendaFilter $filter): Agenda
    {
        $this->access->require(Action::Read, Resource::Services);

        $range = AgendaRange::of($filter->from, $filter->to, $this->calendar->today());
        $schedules = $this->schedules->chosen($filter->scheduleId, $filter->serviceId, $filter->stageId);
        $archived = ArchivedStructure::of($this->structure->archivedIds());

        return Agenda::of(
            $range,
            $this->plannedDates->agendaOf($schedules, $archived, $range),
            RosterReferences::lookedUpIn($this->structure, $schedules),
            $this->calendar,
            SessionAbilities::grantedBy($this->access),
        );
    }
}
