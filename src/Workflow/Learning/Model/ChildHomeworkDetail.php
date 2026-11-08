<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Workflow\People\Model\ChildName;

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
