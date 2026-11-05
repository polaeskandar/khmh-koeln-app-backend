<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

final readonly class NewSchedule
{
    public function __construct(
        public RosterChoice $roster,
        public DetailsChoice $details,
        public RecurrenceChoice $recurrence,
        public TimesChoice $times,
    ) {
    }
}
