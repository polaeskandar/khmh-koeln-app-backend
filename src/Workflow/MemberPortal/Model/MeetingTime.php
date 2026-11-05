<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Meetings\Domain\ValueObject\PlannedDate;

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
        $slot = $date->schedule->timeSlot();

        return new self($date->date->toString(), $slot->startTime->toString(), $slot->endTime?->toString());
    }
}
