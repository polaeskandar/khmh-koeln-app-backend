<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\LessonReading;

final readonly class LessonDetailView
{
    public function __construct(
        public LessonRow $row,
        public ?string $scheduleId,
        public LessonTextView $text,
        public LessonPlanView $plan,
        public LessonMoments $moments,
    ) {
    }

    public static function of(LessonRow $row, LessonReading $reading, LessonDetailAbilities $can): self
    {
        $lesson = $reading->lesson;

        return new self(
            row: $row,
            scheduleId: $lesson->link()->scheduleId?->toRfc4122(),
            text: LessonTextView::of($lesson),
            plan: LessonPlanView::of($lesson, $can),
            moments: LessonMoments::of($reading),
        );
    }
}
