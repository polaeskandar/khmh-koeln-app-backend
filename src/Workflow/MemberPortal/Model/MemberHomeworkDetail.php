<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Workflow\Learning\Model\HomeworkLessonView;
use App\Workflow\Learning\Model\HomeworkTextView;
use App\Workflow\ParentPortal\Model\ParentHomeworkItem;

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
