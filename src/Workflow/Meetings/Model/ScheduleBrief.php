<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Schedule;

final readonly class ScheduleBrief
{
    public function __construct(
        public string $id,
        public string $name,
        public RecurrenceSummary $recurrence,
        public bool $active,
    ) {
    }

    public static function ofRecurring(Schedule $schedule): ?self
    {
        return $schedule->isOneOff() ? null : self::of($schedule);
    }

    public static function of(Schedule $schedule): self
    {
        return new self(
            id: $schedule->id()->toRfc4122(),
            name: $schedule->details()->name,
            recurrence: RecurrenceSummary::of($schedule->recurrence()),
            active: $schedule->isActive(),
        );
    }
}
