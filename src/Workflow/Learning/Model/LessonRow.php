<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Workflow\Identity\Model\AccountReference;

final readonly class LessonRow
{
    public function __construct(
        public LessonSummary $lesson,
        public ?AccountReference $responsible,
        public MeetingView $meeting,
        public LessonHomework $homework,
    ) {
    }
}
