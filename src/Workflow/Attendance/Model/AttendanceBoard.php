<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Workflow\Meetings\Model\HistoryEntry;

final readonly class AttendanceBoard
{
    /**
     * @param list<HistoryEntry> $history
     */
    public function __construct(
        public string $today,
        public BoardSession $session,
        public AttendanceCounts $counts,
        public BoardLists $lists,
        public array $history,
    ) {
    }
}
