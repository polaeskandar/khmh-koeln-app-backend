<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonMeetings;
use App\Context\Meetings\Application\Service\CoveredSchedules;
use App\Context\Meetings\Application\Service\MeetingSchedules;
use App\Context\Meetings\Application\Service\ScheduleRemovals;

final readonly class DeleteScheduleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ScheduleRemovals $removals,
        private LessonMeetings $lessons,
        private MeetingSchedules $schedules,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Sessions);

        $this->access->requireIn(
            Action::Delete,
            Resource::Sessions,
            CoveredSchedules::groupOf($this->schedules->find($id)),
            missing: 'schedule',
        );

        $this->removals->remove($id, lessonsOf: $this->lessons->countForSchedule(...));
    }
}
