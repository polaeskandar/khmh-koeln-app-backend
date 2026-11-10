<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\CoveredSchedules;
use App\Context\Meetings\Application\Service\MeetingSchedules;
use App\Context\Meetings\Application\Service\ScheduleWrites;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\ArchiveGuard;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Problem\Model\Ids;
use App\Workflow\Meetings\Model\ScheduleChanges;
use App\Workflow\Meetings\Model\WrittenSchedule;

final readonly class EditScheduleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSchedules $schedules,
        private ArchiveGuard $archive,
        private StructureLookup $structure,
        private ScheduleWrites $writes,
    ) {
    }

    public function __invoke(string $id, ScheduleChanges $changes): WrittenSchedule
    {
        $this->access->require(Action::Update, Resource::Sessions);

        $schedule = $this->schedules->get(Ids::toUuid($id, notFound: 'schedule'));

        $this->access->requireIn(
            Action::Update,
            Resource::Sessions,
            CoveredSchedules::groupOf($schedule),
            missing: 'schedule',
        );

        $roster = $schedule->roster();
        $this->archive->ensureOpen($roster->serviceId, $roster->stageId);

        return WrittenSchedule::of($this->writes->edit(
            $schedule,
            $this->writes->plan($changes->draft()),
            ArchivedStructure::of($this->structure->archivedIds()),
        ));
    }
}
