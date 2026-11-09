<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Interface\AdministrationLockInterface;
use App\Context\Identity\Domain\Repository\AccountRepositoryInterface;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\RoleChange;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Symfony\Component\Uid\Uuid;

final readonly class RoleAssignments
{
    public function __construct(
        private AccountRepositoryInterface $accounts,
        private RoleRepositoryInterface $roles,
        private LastAdministrator $lastAdministrator,
        private AdministrationLockInterface $administration,
    ) {
    }

    public function give(string $accountId, string $roleId): Role
    {
        $account = $this->account($accountId);
        $role = $this->role($roleId) ?? throw InvalidInput::field('roleId', 'roles.unknown');

        RoleChange::ensureGivable($account, $role);
        $account->assign($role);
        $this->accounts->save($account);

        return $role;
    }

    public function take(string $accountId, string $roleId, string $actorId): void
    {
        $this->administration->whileLocked(function () use ($accountId, $roleId, $actorId): void {
            $account = $this->account($accountId);
            $role = $this->role($roleId) ?? throw NotFound::of('role');
            OwnAdministration::ensureKept($account, $role, Uuid::fromString($actorId));

            if ($this->lastAdministrator->wouldLoseIt($account, $role)) {
                throw new RuleViolation('roles.lastAdministrator');
            }

            RoleChange::ensureHeld($account, $role);
            $account->unassign($role);
            $this->accounts->save($account);
        });
    }

    private function account(string $id): Account
    {
        $account = Uuid::isValid($id) ? $this->accounts->find(Uuid::fromString($id)) : null;

        return $account ?? throw NotFound::of('user');
    }

    private function role(string $id): ?Role
    {
        $roles = Uuid::isValid($id) ? $this->roles->findByIds([Uuid::fromString($id)])->getValues() : [];

        return $roles[0] ?? null;
    }
}
