<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

final readonly class StageHomeworkFacts
{
    /**
     * @param array{published: int, draft: int, archived: int} $tabs
     */
    public function __construct(
        public string $today,
        public array $tabs,
        public int $audienceSize,
        public bool $archived,
    ) {
    }
}
