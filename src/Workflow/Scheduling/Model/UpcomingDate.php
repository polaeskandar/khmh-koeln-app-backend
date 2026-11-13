<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\ValueObject\PlannedDate;

final readonly class UpcomingDate
{
    public function __construct(
        public string $date,
        public string $startTime,
        public string $status,
        public ?string $sessionId,
    ) {
    }

    public static function of(PlannedDate $planned): self
    {
        return new self(
            date: $planned->date->toString(),
            startTime: $planned->plan->timeSlot->startTime->toString(),
            status: $planned->status,
            sessionId: $planned->sessionId,
        );
    }
}
