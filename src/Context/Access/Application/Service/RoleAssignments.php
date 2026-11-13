<?php

declare(strict_types=1);

namespace App\Context\Access\Application\Service;

use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Interface\AdministrationLockInterface;
use App\Context\Access\Domain\Repository\AccountRoleRepositoryInterface;
use App\Context\Access\Domain\Repository\RoleHolderRepositoryInterface;
use App\Context\Access\Domain\Repository\RoleRepositoryInterface;
use App\Context\Access\Domain\ValueObject\RoleChange;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class RoleAssignments
{
    public const string PARENT_ROLE = 'Parent';

    public function __construct(
        private RoleHolderRepositoryInterface $holders,
        private AccountRoleRepositoryInterface $accountRoles,
        private RoleRepositoryInterface $roles,
        private LastAdministrator $lastAdministrator,
        private AdministrationLockInterface $administration,
    ) {
    }

    public function give(Uuid $accountId, string $accountName, string $roleName): void
    {
        $role = $this->role($roleName);

        RoleChange::ensureGivable($this->holders->listRolesOf($accountId), $role, $accountName);
        $this->accountRoles->give($accountId, $role);
    }

    public function take(Uuid $accountId, string $accountName, string $roleName): void
    {
        $this->administration->whileLocked(function () use ($accountId, $accountName, $roleName): void {
            $role = $this->role($roleName);

            if ($this->lastAdministrator->isLostWithout($accountId, $role)) {
                throw new RuleViolation('roles.lastAdministrator');
            }

            RoleChange::ensureHeld($this->holders->listRolesOf($accountId), $role, $accountName);
            $this->accountRoles->take($accountId, $role);
        });
    }

    public function ensureParentRole(Uuid $accountId): void
    {
        $parent = $this->role(self::PARENT_ROLE);

        $held = new ArrayCollection($this->holders->listRolesOf($accountId))->filter(
            static fn (Role $role): bool => $role->id()->equals($parent->id()),
        );

        if (!$held->isEmpty()) {
            return;
        }

        $this->accountRoles->give($accountId, $parent);
    }

    public function giveToNewAccount(Uuid $accountId, ?string $roleName): void
    {
        if ($roleName === null) {
            return;
        }

        $this->accountRoles->give(
            $accountId,
            $this->roles->findByName($roleName) ?? throw InvalidInput::field('role', 'roles.unknown'),
        );
    }

    private function role(string $name): Role
    {
        return $this->roles->findByName($name) ?? throw NotFound::of('role');
    }
}
