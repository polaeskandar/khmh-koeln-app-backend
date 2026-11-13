<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\Enum\RosterMode;
use App\Context\Scheduling\Domain\ValueObject\ScheduleDraft;

final readonly class NewSchedule
{
    public function __construct(
        public RosterChoice $roster,
        public DetailsChoice $details,
        public RecurrenceChoice $recurrence,
        public TimesChoice $times,
    ) {
    }

    public function draft(): ScheduleDraft
    {
        return new ScheduleDraft(
            $this->details->draft(),
            $this->recurrence->draft(),
            $this->times->draft(),
            RosterMode::from($this->roster->mode ?? ''),
        );
    }
}
