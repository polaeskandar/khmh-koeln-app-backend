<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

final readonly class CountedSession
{
    public function __construct(
        public string $sessionId,
        public AttendanceCounts $counts,
    ) {
    }
}
