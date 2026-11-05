<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

final readonly class HomeworkItem
{
    public function __construct(
        public HomeworkSummary $task,
        public LessonOnDate $lesson,
        public ?AudienceCounts $counts,
        public HomeworkAbilities $can,
    ) {
    }
}
