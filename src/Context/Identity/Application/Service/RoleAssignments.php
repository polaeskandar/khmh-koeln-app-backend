<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Interface\AdministrationLockInterface;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\RoleChange;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;

final readonly class RoleAssignments
{
    public function __construct(
        private UserRepositoryInterface $users,
        private RoleRepositoryInterface $roles,
        private LastAdministrator $lastAdministrator,
        private AdministrationLockInterface $administration,
    ) {
    }

    public function give(string $email, string $roleName): void
    {
        $user = $this->account($email);
        $role = $this->role($roleName);

        RoleChange::ensureGivable($user, $role);
        $user->assign($role);
        $this->users->save($user);
    }

    public function take(string $email, string $roleName): void
    {
        $this->administration->whileLocked(function () use ($email, $roleName): void {
            $user = $this->account($email);
            $role = $this->role($roleName);

            if ($this->lastAdministrator->isLostWithout($user, $role)) {
                throw new RuleViolation('roles.lastAdministrator');
            }

            RoleChange::ensureHeld($user, $role);
            $user->unassign($role);
            $this->users->save($user);
        });
    }

    private function account(string $email): User
    {
        return $this->users->findByEmail($email) ?? throw NotFound::of('account');
    }

    private function role(string $name): Role
    {
        return $this->roles->findByName($name) ?? throw NotFound::of('role');
    }
}
