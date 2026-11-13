<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Learning\Domain\Query\LessonQueryInterface;
use App\Context\Scheduling\Application\Service\CoveredSchedules;
use App\Context\Scheduling\Application\Service\MeetingSchedules;
use App\Context\Scheduling\Application\Service\ScheduleRemovals;
use App\Library\Problem\Model\Ids;

final readonly class DeleteScheduleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ScheduleRemovals $removals,
        private LessonQueryInterface $lessons,
        private MeetingSchedules $schedules,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Sessions);

        $schedule = $this->schedules->get(Ids::toUuid($id, notFound: 'schedule'));

        $this->access->requireIn(
            Action::Delete,
            Resource::Sessions,
            CoveredSchedules::groupOf($schedule),
            missing: 'schedule',
        );

        $this->removals->remove($schedule->id(), lessonsOf: $this->lessons->countForSchedule(...));
    }
}
