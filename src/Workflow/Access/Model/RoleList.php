<?php

declare(strict_types=1);

namespace App\Workflow\Access\Model;

final readonly class RoleList
{
    /**
     * @param list<RoleRow> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
