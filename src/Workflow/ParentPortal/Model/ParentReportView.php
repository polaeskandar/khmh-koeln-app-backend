<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

final readonly class ParentReportView
{
    public function __construct(
        public bool $done,
        public ?string $completedAt,
        public bool $completedByYou,
        public ?string $completedBy,
    ) {
    }
}
