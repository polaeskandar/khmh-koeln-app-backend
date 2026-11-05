<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\TimeSlot;
use App\Library\Time\Model\LocalDate;
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
        return self::ofSlot($session->occurrenceDate(), $session->plan()->timeSlot, $startsAt, $endsAt);
    }

    public static function ofSlot(
        LocalDate $date,
        TimeSlot $slot,
        DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
    ): self {
        return new self(
            date: $date->toString(),
            startTime: $slot->startTime->toString(),
            endTime: $slot->endTime?->toString(),
            startsAt: $startsAt,
            endsAt: $endsAt,
        );
    }
}
