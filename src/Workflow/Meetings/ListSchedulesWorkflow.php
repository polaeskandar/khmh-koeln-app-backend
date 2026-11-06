<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSchedules;
use App\Context\Meetings\Application\Service\ScheduleOutlooks;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Time\Client\Calendar;
use App\Workflow\Meetings\Model\RosterReferences;
use App\Workflow\Meetings\Model\ScheduleList;
use App\Workflow\Meetings\Model\ScheduleSummary;

final readonly class ListSchedulesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSchedules $schedules,
        private ScheduleOutlooks $outlooks,
        private StructureLookup $structure,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(?string $serviceId, ?string $stageId): ScheduleList
    {
        $this->access->require(Action::Read, Resource::Services);

        $today = $this->calendar->today();
        $schedules = $this->schedules->listed($serviceId, $stageId);
        $archived = ArchivedStructure::of($this->structure->archivedIds());

        return new ScheduleList($today->toString(), ScheduleSummary::listOf(
            $this->outlooks->of($schedules, $archived, $today),
            RosterReferences::lookedUpIn($this->structure, $schedules),
        ));
    }
}
