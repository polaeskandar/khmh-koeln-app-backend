<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class CurrentAccount
{
    /**
     * @param list<RoleSummary> $roles
     * @param list<PermissionGrant> $permissions
     */
    public function __construct(
        public AccountSummary $account,
        public array $roles,
        public array $permissions,
        public string $today,
    ) {
    }
}
