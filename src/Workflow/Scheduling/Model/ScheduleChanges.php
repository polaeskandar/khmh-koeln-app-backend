<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\Enum\RosterMode;
use App\Context\Scheduling\Domain\ValueObject\ScheduleDraft;

final readonly class ScheduleChanges
{
    public function __construct(
        public DetailsChoice $details,
        public RecurrenceChoice $recurrence,
        public TimesChoice $times,
        public ?string $rosterMode,
    ) {
    }

    public function draft(): ScheduleDraft
    {
        return new ScheduleDraft(
            $this->details->draft(),
            $this->recurrence->draft(),
            $this->times->draft(),
            RosterMode::from($this->rosterMode ?? ''),
        );
    }
}
