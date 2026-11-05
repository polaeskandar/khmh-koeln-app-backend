<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Library\Time\Model\LocalDate;

final readonly class CountedSession
{
    public static function is(Session $session, LocalDate $today): bool
    {
        return match ($session->progress()->status) {
            SessionStatus::Completed, SessionStatus::Active => true,
            SessionStatus::Scheduled => $session->occurrenceDate()->isBefore($today),
            SessionStatus::Canceled => false,
        };
    }
}
