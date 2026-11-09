<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class RolePermissions
{
    public static function roleAllows(Role $role, Action $action, Resource $resource): bool
    {
        $granting = $role->permissions()->filter(
            static fn (Permission $permission): bool => self::grants($permission, $action, $resource),
        );

        return !$granting->isEmpty();
    }

    public static function accountHas(Account $account, Action $action, Resource $resource): bool
    {
        $granting = $account
            ->roles()
            ->filter(static fn (Role $role): bool => self::roleAllows($role, $action, $resource));

        return !$granting->isEmpty();
    }

    public static function activeAccountHas(Account $account, Action $action, Resource $resource): bool
    {
        return $account->status()->isActive() && self::accountHas($account, $action, $resource);
    }

    public static function accountHasWithout(Account $account, Role $left, Action $action, Resource $resource): bool
    {
        $granting = $account
            ->roles()
            ->filter(static fn (Role $role): bool => $role !== $left && self::roleAllows($role, $action, $resource));

        return !$granting->isEmpty();
    }

    private static function grants(Permission $permission, Action $action, Resource $resource): bool
    {
        return $permission->action() === $action && $permission->resource() === $resource;
    }
}
