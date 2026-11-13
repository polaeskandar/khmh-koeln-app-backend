<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\ValueObject\PlannedDate;

final readonly class MeetingTime
{
    public function __construct(
        public string $date,
        public string $startTime,
        public ?string $endTime,
    ) {
    }

    public static function of(PlannedDate $date): self
    {
        $slot = $date->plan->timeSlot;

        return new self($date->date->toString(), $slot->startTime->toString(), $slot->endTime?->toString());
    }
}
