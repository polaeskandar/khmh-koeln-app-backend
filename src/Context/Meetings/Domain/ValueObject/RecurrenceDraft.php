<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Enum\Frequency;

final readonly class RecurrenceDraft
{
    /**
     * @param list<int> $weekdays
     */
    public function __construct(
        public Frequency $frequency,
        public ?int $interval,
        public array $weekdays,
        public ?int $monthWeek,
        public DateSpan $span,
    ) {
    }

    public function isMonthly(): bool
    {
        return $this->frequency === Frequency::Monthly;
    }

    public function toRecurrence(): Recurrence
    {
        $interval = $this->interval ?? 1;

        return $this->isMonthly()
            ? Recurrence::monthly($this->monthWeek ?? 1, $this->weekdays[0] ?? 1, $this->span, interval: $interval)
            : Recurrence::weekly($interval, $this->weekdays, $this->span);
    }
}
