<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

final readonly class ParentAccessList
{
    /**
     * @param list<ParentAccessItem> $items
     * @param list<array{id: string, name: string, email: string}> $accounts
     */
    public function __construct(
        public array $items,
        public array $accounts,
        public string $today,
    ) {
    }
}
