<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Enum\RoleScope;

final readonly class PermissionScopes
{
    public static function of(User $user, Action $action, Resource $resource): ?RoleScope
    {
        $scopes = $user
            ->roles()
            ->filter(static fn (Role $role): bool => RolePermissions::roleAllows($role, $action, $resource))
            ->map(static fn (Role $role): RoleScope => $role->scope());

        if ($scopes->contains(RoleScope::Church)) {
            return RoleScope::Church;
        }

        return $scopes->isEmpty() ? null : RoleScope::AssignedGroups;
    }
}
