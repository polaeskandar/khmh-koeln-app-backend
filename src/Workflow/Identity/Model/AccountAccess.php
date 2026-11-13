<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Workflow\Access\Model\RoleSummary;
use App\Workflow\Organization\Model\AssignedGroup;

final readonly class AccountAccess
{
    /**
     * @param list<RoleSummary> $roles
     * @param list<AccountPermission> $permissions
     * @param list<AssignedGroup> $assignments
     */
    public function __construct(
        public array $roles,
        public array $permissions,
        public array $assignments,
    ) {
    }
}
