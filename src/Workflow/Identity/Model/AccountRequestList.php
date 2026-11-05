<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class AccountRequestList
{
    /**
     * @param list<AccountRequest> $items
     * @param list<RoleSummary> $roles
     */
    public function __construct(
        public array $items,
        public array $roles,
        public string $today,
    ) {
    }
}
