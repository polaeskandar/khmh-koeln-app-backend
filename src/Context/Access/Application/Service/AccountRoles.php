<?php

declare(strict_types=1);

namespace App\Context\Access\Application\Service;

use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\Interface\AdministrationLockInterface;
use App\Context\Access\Domain\Repository\AccountRoleRepositoryInterface;
use App\Context\Access\Domain\Repository\RoleHolderRepositoryInterface;
use App\Context\Access\Domain\Repository\RoleRepositoryInterface;
use App\Context\Access\Domain\ValueObject\RoleChange;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use Symfony\Component\Uid\Uuid;

final readonly class AccountRoles
{
    public function __construct(
        private RoleHolderRepositoryInterface $holders,
        private AccountRoleRepositoryInterface $accountRoles,
        private RoleRepositoryInterface $roles,
        private LastAdministrator $lastAdministrator,
        private AdministrationLockInterface $administration,
    ) {
    }

    public function give(Uuid $accountId, string $accountName, ?Uuid $roleId): Role
    {
        $role = ($roleId === null ? null : $this->role($roleId)) ?? throw InvalidInput::field(
            'roleId',
            'roles.unknown',
        );

        RoleChange::ensureGivable($this->holders->listRolesOf($accountId), $role, $accountName);
        $this->accountRoles->give($accountId, $role);

        return $role;
    }

    public function take(Uuid $accountId, string $accountName, Uuid $roleId, Uuid $actorId): void
    {
        $this->administration->whileLocked(function () use ($accountId, $accountName, $roleId, $actorId): void {
            $role = $this->role($roleId) ?? throw NotFound::of('role');
            $held = $this->holders->listRolesOf($accountId);

            if ($accountId->equals($actorId)) {
                self::ensureOwnAdministrationKept($held, $role);
            }

            if ($this->lastAdministrator->isLostWithout($accountId, $role)) {
                throw new RuleViolation('roles.lastAdministrator');
            }

            RoleChange::ensureHeld($held, $role, $accountName);
            $this->accountRoles->take($accountId, $role);
        });
    }

    /**
     * @param list<Role> $held
     */
    private static function ensureOwnAdministrationKept(array $held, Role $role): void
    {
        if (!RolePermissions::isGrantedToRole($role, Action::Update, Resource::Roles)) {
            return;
        }

        if (!RolePermissions::isGrantedWithout($held, $role, Action::Update, Resource::Roles)) {
            throw new RuleViolation('roles.ownAdminRole');
        }
    }

    private function role(Uuid $id): ?Role
    {
        return $this->roles->findByIds([$id])->getValues()[0] ?? null;
    }
}
