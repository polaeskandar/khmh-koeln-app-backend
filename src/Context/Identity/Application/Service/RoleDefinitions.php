<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Interface\AdministrationLockInterface;
use App\Context\Identity\Domain\Repository\PermissionRepositoryInterface;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Context\Identity\Domain\ValueObject\PermissionSelection;
use App\Context\Identity\Domain\ValueObject\RoleName;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\RuleViolation;

final readonly class RoleDefinitions
{
    public function __construct(
        private RoleRepositoryInterface $roles,
        private PermissionRepositoryInterface $permissions,
        private RoleCatalog $catalog,
        private LastAdministrator $lastAdministrator,
        private AdministrationLockInterface $administration,
    ) {
    }

    /**
     * @param list<array{action: string|null, resource: string|null}> $permissions
     */
    public function create(string $name, array $permissions): Role
    {
        self::check($name, $permissions);
        $role = new Role(RoleName::of($name));
        $selection = PermissionSelection::of($permissions);

        $this->administration->whileLocked(function () use ($role, $selection): void {
            $this->ensureFree($role->name(), $role);
            $role->replacePermissions($selection->permissionsIn($this->permissions->catalog()));
            $this->roles->save($role);
        });

        return $role;
    }

    /**
     * @param list<array{action: string|null, resource: string|null}> $permissions
     */
    public function change(string $id, string $name, array $permissions): void
    {
        $this->administration->whileLocked(function () use ($id, $name, $permissions): void {
            $role = $this->catalog->find($id);
            self::check($name, $permissions);
            $roleName = RoleName::of($name);
            $selection = PermissionSelection::of($permissions);
            $this->ensureFree($roleName, $role);

            if ($this->lastAdministrator->isKeptOnlyBy($role) && !$selection->grants(Action::Update, Resource::Roles)) {
                throw new RuleViolation('roles.lastAdministrator');
            }

            $role->rename($roleName);
            $role->replacePermissions($selection->permissionsIn($this->permissions->catalog()));
            $this->roles->save($role);
        });
    }

    public function remove(string $id): void
    {
        $this->administration->whileLocked(function () use ($id): void {
            $role = $this->catalog->find($id);

            if ($this->lastAdministrator->isKeptOnlyBy($role)) {
                throw new RuleViolation('roles.lastAdministrator');
            }

            $this->roles->remove($role);
        });
    }

    /**
     * @param list<array{action: string|null, resource: string|null}> $permissions
     */
    private static function check(string $name, array $permissions): void
    {
        InvalidInput::all(
            static fn (): RoleName => RoleName::of($name),
            static fn (): PermissionSelection => PermissionSelection::of($permissions),
        );
    }

    private function ensureFree(RoleName $name, Role $role): void
    {
        $holder = $this->roles->findByName($name->value) ?? $role;

        if ($holder === $role) {
            return;
        }

        throw InvalidInput::field('name', 'roles.nameTaken', ['name' => $holder->name()->value]);
    }
}
