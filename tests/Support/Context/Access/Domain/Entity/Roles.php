<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Access\Domain\Entity;

use App\Context\Access\Domain\Entity\Permission;
use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Enum\RoleScope;
use App\Context\Access\Domain\ValueObject\RoleName;

final readonly class Roles
{
    public static function named(string $name, Permission ...$permissions): Role
    {
        $role = new Role(RoleName::of($name), RoleScope::Church);

        foreach ($permissions as $permission) {
            $role->grant($permission);
        }

        return $role;
    }
}
