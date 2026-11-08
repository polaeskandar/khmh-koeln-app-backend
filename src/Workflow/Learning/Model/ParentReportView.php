<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use DateTimeImmutable;

final readonly class ParentReportView
{
    public function __construct(
        public bool $done,
        public ?DateTimeImmutable $completedAt,
        public bool $completedByYou,
        public ?string $completedBy,
    ) {
    }
}
