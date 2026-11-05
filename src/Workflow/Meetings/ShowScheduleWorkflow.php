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
use App\Workflow\Meetings\Model\DatedSchedule;
use App\Workflow\Meetings\Model\RosterReferences;
use App\Workflow\Meetings\Model\RosterSizes;
use App\Workflow\Meetings\Model\ScheduleSummary;

final readonly class ShowScheduleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ScheduleOutlooks $outlooks,
        private StructureLookup $structure,
        private Attendees $attendees,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $id): DatedSchedule
    {
        $this->access->require(Action::Read, Resource::Services);

        $today = $this->calendar->today();
        $outlook = $this->outlooks->found($id, ArchivedStructure::of($this->structure->archivedIds()), $today);
        $schedules = ScheduleSummary::schedulesOf([$outlook]);

        return new DatedSchedule($today->toString(), ScheduleSummary::of(
            $outlook,
            RosterReferences::lookedUpIn($this->structure, $schedules),
            RosterSizes::lookedUpIn($this->attendees, $schedules, $today),
            canManage: $this->access->allows(Action::Update, Resource::Sessions),
        ));
    }
}
