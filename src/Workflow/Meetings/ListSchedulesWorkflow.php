<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\ScheduleOutlooks;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Library\Time\Client\Calendar;
use App\Workflow\Meetings\Model\RosterReferences;
use App\Workflow\Meetings\Model\RosterSizes;
use App\Workflow\Meetings\Model\ScheduleList;
use App\Workflow\Meetings\Model\ScheduleSummary;

final readonly class ListSchedulesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ScheduleOutlooks $outlooks,
        private StructureLookup $structure,
        private Attendees $attendees,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(?string $serviceId, ?string $stageId): ScheduleList
    {
        $this->access->require(Action::Read, Resource::Services);

        $today = $this->calendar->today();
        $archived = ArchivedStructure::of($this->structure->archivedIds());
        $outlooks = $this->outlooks->listed($serviceId, $stageId, $archived, $today);
        $schedules = ScheduleSummary::schedulesOf($outlooks);

        return new ScheduleList($today->toString(), ScheduleSummary::listOf(
            $outlooks,
            RosterReferences::lookedUpIn($this->structure, $schedules),
            RosterSizes::lookedUpIn($this->attendees, $schedules, $today),
            canManage: $this->access->allows(Action::Update, Resource::Sessions),
        ));
    }
}
