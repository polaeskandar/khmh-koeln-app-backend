<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Request;

use App\Workflow\Learning\Model\LessonMeetingChoice;

final readonly class LessonMeetingRequest
{
    public function __construct(
        public ?string $scheduleId = null,
        public ?string $sessionId = null,
        public ?string $date = null,
    ) {
    }

    public function choice(): LessonMeetingChoice
    {
        return new LessonMeetingChoice($this->scheduleId, $this->sessionId, $this->date);
    }
}
