<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

final readonly class SessionDetail
{
    /**
     * @param list<HistoryEntry> $history
     */
    public function __construct(
        public string $today,
        public SessionView $session,
        public SessionState $state,
        public ?ScheduleBrief $schedule,
        public array $history,
    ) {
    }
}
