<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

final readonly class LessonDetail
{
    public function __construct(
        public LessonStageView $stage,
        public string $today,
        public LessonDetailView $lesson,
        public LessonDetailAbilities $can,
    ) {
    }
}
