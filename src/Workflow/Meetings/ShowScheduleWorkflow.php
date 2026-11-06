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
use App\Workflow\Meetings\Model\DatedSchedule;
use App\Workflow\Meetings\Model\RosterReferences;
use App\Workflow\Meetings\Model\ScheduleSummary;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ShowScheduleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSchedules $schedules,
        private ScheduleOutlooks $outlooks,
        private StructureLookup $structure,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $id): DatedSchedule
    {
        $this->access->require(Action::Read, Resource::Services);

        $today = $this->calendar->today();
        $schedule = $this->schedules->find($id);
        $archived = ArchivedStructure::of($this->structure->archivedIds());

        return new DatedSchedule($today->toString(), ScheduleSummary::of(
            $this->outlooks->one($schedule, $archived, $today),
            RosterReferences::lookedUpIn($this->structure, new ArrayCollection([$schedule])),
        ));
    }
}
