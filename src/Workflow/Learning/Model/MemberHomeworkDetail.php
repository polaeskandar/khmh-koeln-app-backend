<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

final readonly class MemberHomeworkDetail
{
    public function __construct(
        public string $today,
        public ParentHomeworkItem $item,
        public HomeworkTextView $text,
        public HomeworkLessonView $lesson,
    ) {
    }
}
