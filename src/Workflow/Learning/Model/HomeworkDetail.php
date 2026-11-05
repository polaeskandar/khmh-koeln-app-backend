<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

final readonly class HomeworkDetail
{
    public function __construct(
        public HomeworkView $task,
        public HomeworkLessonView $lesson,
        public LessonStageView $stage,
        public HomeworkFacts $facts,
        public HomeworkAbilities $can,
    ) {
    }
}
