<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\RuleViolation;

final readonly class RoleAssignments
{
    public function __construct(
        private UserRepositoryInterface $users,
        private LastAdministrator $lastAdministrator,
    ) {
    }

    public function give(User $user, Role $role): void
    {
        if ($user->status()->isPending()) {
            throw new RuleViolation('roles.accountPending');
        }

        $user->assign($role);
        $this->users->save($user);
    }

    public function take(User $user, Role $role): void
    {
        if ($this->lastAdministrator->wouldLoseIt($user, $role)) {
            throw new RuleViolation('roles.lastAdministrator');
        }

        $user->unassign($role);
        $this->users->save($user);
    }
}
