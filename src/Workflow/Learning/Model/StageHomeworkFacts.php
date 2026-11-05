<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

final readonly class StageHomeworkFacts
{
    /**
     * @param array<string, int> $tabs
     */
    public function __construct(
        public string $today,
        public array $tabs,
        public int $audienceSize,
        public bool $canCreate,
    ) {
    }
}
