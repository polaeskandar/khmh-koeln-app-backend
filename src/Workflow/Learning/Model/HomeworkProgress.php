<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

final readonly class HomeworkProgress
{
    /**
     * @param list<AudienceRow> $rows
     */
    public function __construct(
        public LessonStageView $stage,
        public string $today,
        public HomeworkItem $item,
        public HomeworkTextView $text,
        public array $rows,
    ) {
    }
}
