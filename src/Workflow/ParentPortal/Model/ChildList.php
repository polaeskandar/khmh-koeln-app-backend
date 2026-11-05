<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

final readonly class ChildList
{
    /**
     * @param list<ChildSummary> $items
     */
    public function __construct(
        public string $today,
        public array $items,
    ) {
    }
}
