<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Session;
use DateTimeImmutable;

final readonly class SessionTimes
{
    public function __construct(
        public string $date,
        public string $startTime,
        public ?string $endTime,
        public DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
    ) {
    }

    public static function of(Session $session, DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): self
    {
        $slot = $session->schedule()->timeSlot();

        return new self(
            date: $session->occurrenceDate()->toString(),
            startTime: $slot->startTime->toString(),
            endTime: $slot->endTime?->toString(),
            startsAt: $startsAt,
            endsAt: $endsAt,
        );
    }
}
