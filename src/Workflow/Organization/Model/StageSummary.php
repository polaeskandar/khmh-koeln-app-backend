<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

final readonly class StageSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public int $position,
    ) {
    }
}
