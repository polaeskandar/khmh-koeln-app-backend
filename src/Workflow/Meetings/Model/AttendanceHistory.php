<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

final readonly class AttendanceHistory
{
    /**
     * @param list<HistorySession> $sessions
     * @param list<HistoryRow> $rows
     */
    public function __construct(
        public string $today,
        public array $sessions,
        public array $rows,
    ) {
    }
}
