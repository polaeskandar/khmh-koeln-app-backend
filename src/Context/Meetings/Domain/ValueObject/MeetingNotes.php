<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

final readonly class MeetingNotes
{
    /**
     * @param list<MeetingNote> $notes
     * @param list<CoveredSession> $sessions
     */
    public function __construct(
        public array $notes,
        public array $sessions,
    ) {
    }
}
