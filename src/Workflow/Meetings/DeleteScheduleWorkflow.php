<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LessonMeetings;
use App\Context\Meetings\Application\Service\ScheduleDeletions;

final readonly class DeleteScheduleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ScheduleDeletions $deletions,
        private LessonMeetings $lessons,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Sessions);

        $this->deletions->delete($id, lessonsOf: $this->lessons->countForSchedule(...));
    }
}
