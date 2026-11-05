<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;

final readonly class LessonDetailView
{
    public function __construct(
        public LessonRow $row,
        public ?string $scheduleId,
        public LessonTextView $text,
        public LessonPlanView $plan,
        public LessonStateView $state,
    ) {
    }

    public static function of(LessonRow $row, Lesson $lesson, LessonPlanView $plan): self
    {
        return new self(
            row: $row,
            scheduleId: $lesson->link()->scheduleId?->toRfc4122(),
            text: LessonTextView::of($lesson),
            plan: $plan,
            state: LessonStateView::of($lesson),
        );
    }
}
