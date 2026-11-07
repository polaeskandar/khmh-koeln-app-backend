<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\Meetings\Domain\ValueObject\ScheduleDraft;

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
