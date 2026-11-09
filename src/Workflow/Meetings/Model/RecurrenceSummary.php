<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Enum\Frequency;
use App\Context\Meetings\Domain\ValueObject\Recurrence;

final readonly class RecurrenceSummary
{
    /**
     * @param list<int> $weekdays
     * @param array{startsOn: string, endsOn: string|null} $span
     */
    public function __construct(
        public Frequency $frequency,
        public int $interval,
        public array $weekdays,
        public ?int $monthWeek,
        public array $span,
    ) {
    }

    public static function of(Recurrence $recurrence): self
    {
        return new self(
            frequency: $recurrence->frequency,
            interval: $recurrence->interval,
            weekdays: $recurrence->weekdays(),
            monthWeek: $recurrence->monthWeek,
            span: [
                'startsOn' => $recurrence->span->startsOn->toString(),
                'endsOn' => $recurrence->span->endsOn?->toString(),
            ],
        );
    }
}
