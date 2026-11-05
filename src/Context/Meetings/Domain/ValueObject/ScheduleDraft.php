<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

final readonly class ScheduleDraft
{
    public function __construct(
        public Roster $roster,
        public DetailsDraft $details,
        public RecurrenceDraft $recurrence,
        public TimesDraft $times,
    ) {
    }
}
