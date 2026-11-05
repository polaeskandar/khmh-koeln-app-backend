<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\MeetingChoice;

final readonly class LessonMeetingChoice
{
    public function __construct(
        public ?string $scheduleId,
        public ?string $sessionId,
        public ?string $date,
    ) {
    }

    public function choice(): MeetingChoice
    {
        return new MeetingChoice($this->scheduleId, $this->sessionId, $this->date);
    }
}
