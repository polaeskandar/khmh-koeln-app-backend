<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Enum\Frequency;
use App\Context\Scheduling\Domain\Validation\RecurrenceRule;
use LogicException;

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
        RecurrenceRule::ensureValid($this);
        $interval = $this->interval ?? throw new LogicException('Check the draft before building its recurrence.');

        return $this->isMonthly()
            ? Recurrence::monthly(
                $this->monthWeek ?? throw new LogicException('Check the draft before building its recurrence.'),
                $this->weekdays[0] ?? throw new LogicException('Check the draft before building its recurrence.'),
                $this->span,
                interval: $interval,
            )
            : Recurrence::weekly($interval, $this->weekdays, $this->span);
    }
}
