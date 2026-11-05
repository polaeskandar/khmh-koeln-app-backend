<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use DateTimeImmutable;

final readonly class HistorySession
{
    public function __construct(
        public string $id,
        public string $date,
        public DateTimeImmutable $startsAt,
        public int $present,
    ) {
    }
}
