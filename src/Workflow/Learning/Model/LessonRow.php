<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Workflow\Identity\Model\AccountReference;

final readonly class LessonRow
{
    /**
     * @param list<AccountReference> $collaborators
     */
    public function __construct(
        public LessonSummary $lesson,
        public ?AccountReference $responsible,
        public array $collaborators,
        public NoMeeting|OffScheduleMeeting|PlannedMeeting|SessionMeeting $meeting,
        public LessonHomework $homework,
    ) {
    }
}
