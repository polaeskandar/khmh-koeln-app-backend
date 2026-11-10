<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Request;

use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Workflow\Learning\Model\LessonChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class LessonRequest
{
    public function __construct(
        #[Assert\Valid]
        public LessonDetailsRequest $details = new LessonDetailsRequest(),
        public LessonMeetingRequest $meeting = new LessonMeetingRequest(),
        #[Assert\Valid]
        public LessonPlanRequest $plan = new LessonPlanRequest(),
        public ?string $responsibleId = null,
        #[Assert\Choice(choices: [
            LessonStatus::Draft->value,
            LessonStatus::Ready->value,
        ], message: 'validation.invalidChoice')]
        public ?string $status = null,
    ) {
    }

    public function lesson(): LessonChoice
    {
        return new LessonChoice(
            $this->details->choice(),
            $this->meeting->choice(),
            $this->plan->choice(),
            $this->responsibleId,
            $this->status,
        );
    }
}
