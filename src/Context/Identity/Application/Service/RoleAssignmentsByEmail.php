<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Interface\AdministrationLockInterface;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\RoleChange;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;

final readonly class RoleAssignmentsByEmail
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
        private RoleRepositoryInterface $roles,
        private LastAdministrator $lastAdministrator,
        private AdministrationLockInterface $administration,
    ) {
    }

    public function give(string $email, string $roleName): void
    {
        $account = $this->account($email);
        $role = $this->role($roleName);

        RoleChange::ensureGivable($account, $role);
        $account->assign($role);
        $this->accounts->save($account);
    }

    public function take(string $email, string $roleName): void
    {
        $this->administration->whileLocked(function () use ($email, $roleName): void {
            $account = $this->account($email);
            $role = $this->role($roleName);

            if ($this->lastAdministrator->wouldLoseIt($account, $role)) {
                throw new RuleViolation('roles.lastAdministrator');
            }

            RoleChange::ensureHeld($account, $role);
            $account->unassign($role);
            $this->accounts->save($account);
        });
    }

    private function account(string $email): Account
    {
        return $this->accounts->findByEmail($email) ?? throw NotFound::of('account');
    }

    private function role(string $name): Role
    {
        return $this->roles->findByName($name) ?? throw NotFound::of('role');
    }
}
