<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

final readonly class LessonDraft
{
    public function __construct(
        public TextDraft $text,
        public MeetingChoice $meeting,
        public PlanDraft $plan,
        public ?string $status,
    ) {
    }
}
