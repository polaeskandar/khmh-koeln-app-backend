<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class StaffAccountList
{
    /**
     * @param list<StaffAccount> $items
     */
    public function __construct(
        public array $items,
        public string $today,
    ) {
    }
}
