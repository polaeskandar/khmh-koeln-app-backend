<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Library\Problem\Exception\RuleViolation;

final readonly class ScheduleUse
{
    public function __construct(
        public int $sessions,
        public int $lessons,
    ) {
    }

    public function ensureUnused(): void
    {
        if ($this->sessions > 0) {
            throw new RuleViolation('meetings.scheduleHasSessions', ['count' => $this->sessions]);
        }

        if ($this->lessons > 0) {
            throw new RuleViolation('meetings.scheduleHasLessons', ['count' => $this->lessons]);
        }
    }
}
