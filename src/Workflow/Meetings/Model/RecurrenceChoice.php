<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Enum\Frequency;
use App\Context\Meetings\Domain\ValueObject\RecurrenceDraft;

final readonly class RecurrenceChoice
{
    /**
     * @param list<int> $weekdays
     */
    public function __construct(
        public ?string $frequency,
        public ?int $interval,
        public array $weekdays,
        public ?int $monthWeek,
        public SpanChoice $span,
    ) {
    }

    public function draft(): RecurrenceDraft
    {
        return new RecurrenceDraft(
            Frequency::from($this->frequency ?? ''),
            $this->interval,
            $this->weekdays,
            $this->monthWeek,
            $this->span->span(),
        );
    }
}
