<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

final readonly class HistorySession
{
    public function __construct(
        public string $id,
        public string $date,
        public string $startsAt,
        public int $present,
    ) {
    }
}
