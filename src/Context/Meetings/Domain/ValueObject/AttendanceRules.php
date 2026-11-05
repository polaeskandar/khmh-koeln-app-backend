<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Model\LocalDate;

final readonly class AttendanceRules
{
    public static function ensureOpenable(SessionStatus $status, LocalDate $occurrenceDate, LocalDate $today): void
    {
        $refusal = match ($status) {
            SessionStatus::Canceled => 'meetings.sessionCanceled',
            SessionStatus::Completed => 'meetings.attendanceClosed',
            default => $today->isBefore($occurrenceDate) ? 'meetings.opensOnTheDay' : null,
        };

        if ($refusal !== null) {
            throw new RuleViolation($refusal);
        }
    }

    public static function ensureClosable(SessionStatus $status): void
    {
        if ($status !== SessionStatus::Active) {
            throw new RuleViolation('meetings.onlyActiveCanClose');
        }
    }

    public static function ensureReopenable(SessionStatus $status): void
    {
        if ($status !== SessionStatus::Completed) {
            throw new RuleViolation('meetings.onlyCompletedCanReopen');
        }
    }
}
