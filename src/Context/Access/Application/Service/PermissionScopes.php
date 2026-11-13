<?php

declare(strict_types=1);

namespace App\Context\Access\Application\Service;

use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\Enum\RoleScope;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class PermissionScopes
{
    /**
     * @param list<Role> $roles an account's roles
     */
    public static function of(array $roles, Action $action, Resource $resource): ?RoleScope
    {
        $scopes = new ArrayCollection($roles)->filter(
            static fn (Role $role): bool => RolePermissions::isGrantedToRole($role, $action, $resource),
        )->map(static fn (Role $role): RoleScope => $role->scope());

        if ($scopes->contains(RoleScope::Church)) {
            return RoleScope::Church;
        }

        return $scopes->isEmpty() ? null : RoleScope::AssignedGroups;
    }
}
