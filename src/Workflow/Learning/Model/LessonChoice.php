<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\LessonDraft;

final readonly class LessonChoice
{
    public function __construct(
        public LessonDetailsChoice $details,
        public LessonMeetingChoice $meeting,
        public LessonPlanChoice $plan,
        public ?string $responsibleId,
        public ?string $status,
    ) {
    }

    public function draft(): LessonDraft
    {
        return new LessonDraft($this->details->draft(), $this->meeting->choice(), $this->plan->draft(), $this->status);
    }
}
