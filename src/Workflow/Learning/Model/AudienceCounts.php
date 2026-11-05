<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

final readonly class AudienceCounts
{
    public function __construct(
        public int $assigned,
        public int $completed,
        public int $completedAfterDueDate,
        public int $withoutParentAccount,
        public int $reportedTotal,
    ) {
    }

    public function notCompleted(): int
    {
        return $this->assigned - $this->completed;
    }
}
