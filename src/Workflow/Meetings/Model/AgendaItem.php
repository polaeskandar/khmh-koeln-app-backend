<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

final readonly class AgendaItem
{
    private function __construct(
        public SessionView|PlannedSession $entry,
        public SessionTimes $times,
        public string $title,
        public string $id,
        public ?AttendanceCounts $counts,
    ) {
    }

    public static function session(SessionView $view, ?AttendanceCounts $counts): self
    {
        return new self($view, $view->times, $view->details->title, $view->details->id, $counts);
    }

    public static function planned(PlannedSession $planned): self
    {
        $schedule = $planned->schedule;

        return new self($planned, $planned->times, $schedule->name, $schedule->id, counts: null);
    }
}
