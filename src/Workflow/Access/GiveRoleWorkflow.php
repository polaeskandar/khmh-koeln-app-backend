<?php

declare(strict_types=1);

namespace App\Workflow\Access;

use App\Context\Access\Application\Service\RoleAssignments;
use App\Context\Identity\Application\Service\AccountLookup;

final readonly class GiveRoleWorkflow
{
    public function __construct(
        private RoleAssignments $assignments,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $username, string $role): void
    {
        $account = $this->accounts->getByUsername($username);

        $this->assignments->give($account->id(), $account->username()->value, $role);
    }
}
