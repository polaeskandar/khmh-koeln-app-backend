<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Library\Problem\Exception\RuleViolation;

final readonly class MarkingRules
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
