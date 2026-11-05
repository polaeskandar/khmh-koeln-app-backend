<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

final readonly class ChildHomeworkList
{
    /**
     * @param list<ParentHomeworkItem> $items
     */
    public function __construct(
        public string $today,
        public array $items,
    ) {
    }
}
