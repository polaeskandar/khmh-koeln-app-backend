<?php

declare(strict_types=1);

namespace App\Context\Access\Application\Service;

use App\Context\Access\Domain\Entity\Permission;
use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RolePermissions
{
    public static function isGrantedToRole(Role $role, Action $action, Resource $resource): bool
    {
        $granting = $role->permissions()->filter(
            static fn (Permission $permission): bool => self::grants($permission, $action, $resource),
        );

        return !$granting->isEmpty();
    }

    /**
     * @param list<Role> $roles an account's roles
     */
    public static function isGrantedToAny(array $roles, Action $action, Resource $resource): bool
    {
        $granting = new ArrayCollection($roles)->filter(
            static fn (Role $role): bool => self::isGrantedToRole($role, $action, $resource),
        );

        return !$granting->isEmpty();
    }

    /**
     * @param list<Role> $roles an account's roles
     */
    public static function isGrantedWithout(array $roles, Role $left, Action $action, Resource $resource): bool
    {
        $others = new ArrayCollection($roles)->filter(
            static fn (Role $role): bool => !$role->id()->equals($left->id()),
        );

        return self::isGrantedToAny($others->getValues(), $action, $resource);
    }

    private static function grants(Permission $permission, Action $action, Resource $resource): bool
    {
        return $permission->action() === $action && $permission->resource() === $resource;
    }
}
