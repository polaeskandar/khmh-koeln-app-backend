<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

final readonly class SessionView
{
    public function __construct(
        public SessionDetails $details,
        public ScheduleRoster $roster,
        public SessionTimes $times,
        public SessionProgress $progress,
    ) {
    }
}
