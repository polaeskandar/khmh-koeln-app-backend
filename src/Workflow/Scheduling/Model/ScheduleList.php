<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

final readonly class ScheduleList
{
    /**
     * @param list<ScheduleSummary> $items
     */
    public function __construct(
        public string $today,
        public array $items,
    ) {
    }
}
