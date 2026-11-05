<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

use App\Workflow\Learning\Model\HomeworkLessonView;
use App\Workflow\Learning\Model\HomeworkTextView;

final readonly class ChildHomeworkDetail
{
    public function __construct(
        public string $today,
        public ChildName $child,
        public ParentHomeworkItem $item,
        public HomeworkTextView $text,
        public HomeworkLessonView $lesson,
    ) {
    }
}
