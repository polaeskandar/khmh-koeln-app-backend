<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Validation;

use App\Context\Scheduling\Domain\Enum\SessionStatus;
use App\Library\Problem\Exception\RuleViolation;

final readonly class AttendanceRule
{
    public static function ensureMarkable(SessionStatus $status): void
    {
        $refusal = match ($status) {
            SessionStatus::Scheduled => 'attendance.openFirst',
            SessionStatus::Completed => 'attendance.closedReopen',
            SessionStatus::Canceled => 'meetings.sessionCanceled',
            SessionStatus::Active => null,
        };

        if ($refusal !== null) {
            throw new RuleViolation($refusal);
        }
    }
}
