<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\ArchiveGuard;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Scheduling\Application\Service\CoveredSchedules;
use App\Context\Scheduling\Application\Service\MeetingSchedules;
use App\Context\Scheduling\Application\Service\ScheduleWrites;
use App\Context\Scheduling\Domain\ValueObject\ArchivedStructure;
use App\Library\Problem\Model\Ids;
use App\Workflow\Scheduling\Model\WrittenSchedule;

final readonly class ActivateScheduleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSchedules $schedules,
        private ArchiveGuard $archive,
        private StructureLookup $structure,
        private ScheduleWrites $writes,
    ) {
    }

    public function __invoke(string $id): WrittenSchedule
    {
        $this->access->require(Action::Update, Resource::Sessions);

        $schedule = $this->schedules->get(Ids::toUuid($id, notFound: 'schedule'));

        $this->access->requireIn(
            Action::Update,
            Resource::Sessions,
            CoveredSchedules::groupOf($schedule),
            missing: 'schedule',
        );

        $this->archive->ensureOpen($schedule->roster()->serviceId, $schedule->roster()->stageId);

        return WrittenSchedule::of($this->writes->activate(
            $schedule,
            ArchivedStructure::of($this->structure->archivedIds()),
        ));
    }
}
