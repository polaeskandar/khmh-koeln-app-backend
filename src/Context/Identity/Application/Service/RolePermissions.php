<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class RolePermissions
{
    public static function isGrantedToRole(Role $role, Action $action, Resource $resource): bool
    {
        $granting = $role->permissions()->filter(
            static fn (Permission $permission): bool => self::grants($permission, $action, $resource),
        );

        return !$granting->isEmpty();
    }

    public static function isGrantedToAccount(User $user, Action $action, Resource $resource): bool
    {
        $granting = $user->roles()->filter(
            static fn (Role $role): bool => self::isGrantedToRole($role, $action, $resource),
        );

        return !$granting->isEmpty();
    }

    public static function isGrantedToActiveAccount(User $user, Action $action, Resource $resource): bool
    {
        return $user->status()->isActive() && self::isGrantedToAccount($user, $action, $resource);
    }

    public static function isGrantedToAccountWithout(User $user, Role $left, Action $action, Resource $resource): bool
    {
        $granting = $user->roles()->filter(
            static fn (Role $role): bool => $role !== $left && self::isGrantedToRole($role, $action, $resource),
        );

        return !$granting->isEmpty();
    }

    private static function grants(Permission $permission, Action $action, Resource $resource): bool
    {
        return $permission->action() === $action && $permission->resource() === $resource;
    }
}
