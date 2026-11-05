<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\ValueObject\ScheduleOutlook;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ScheduleActivity
{
    /**
     * @param list<UpcomingDate> $nextDates
     */
    public function __construct(
        public bool $active,
        public bool $generating,
        public array $nextDates,
        public int $sessionCount,
        public ?int $rosterSize,
    ) {
    }

    public static function of(ScheduleOutlook $outlook, ?int $rosterSize): self
    {
        return new self(
            active: $outlook->schedule->isActive(),
            generating: $outlook->generating,
            nextDates: new ArrayCollection($outlook->nextDates)->map(UpcomingDate::of(...))->getValues(),
            sessionCount: $outlook->sessionCount,
            rosterSize: $rosterSize,
        );
    }
}
