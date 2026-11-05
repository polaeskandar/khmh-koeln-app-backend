<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

final readonly class AgendaItem
{
    private function __construct(
        public SessionView|PlannedSession $entry,
        public string $sortKey,
    ) {
    }

    public static function session(SessionView $view): self
    {
        return new self($view, self::sortKeyOf($view->times, $view->details->title, $view->details->id));
    }

    public static function planned(PlannedSession $planned): self
    {
        $schedule = $planned->schedule;

        return new self($planned, self::sortKeyOf($planned->times, $schedule->name, $schedule->id));
    }

    private static function sortKeyOf(SessionTimes $times, string $title, string $id): string
    {
        return $times->startsAt . '|' . $title . '|' . $id;
    }
}
