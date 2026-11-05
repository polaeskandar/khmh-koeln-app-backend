<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

final readonly class HomeworkFacts
{
    public function __construct(
        public string $today,
        public string $minDueDate,
        public int $audienceSize,
        public ?AudienceCounts $counts,
    ) {
    }
}
