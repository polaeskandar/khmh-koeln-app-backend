<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Workflow\Meetings\Model\AttendanceHistory;
use App\Workflow\Meetings\Model\HistoryRow;
use App\Workflow\Meetings\Model\HistorySession;

final readonly class AttendanceHistoryResponse
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

    public static function of(AttendanceHistory $history): self
    {
        return new self($history->today, $history->sessions, $history->rows);
    }
}
