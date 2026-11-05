<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Library\Problem\Exception\RuleViolation;

final readonly class RoleChange
{
    public static function ensureGivable(User $user, Role $role): void
    {
        if ($user->status()->isPending()) {
            throw new RuleViolation('roles.accountPending');
        }

        if ($user->roles()->contains($role)) {
            throw new RuleViolation('roles.alreadyHasRole', ['name' => $user->profile()->displayName->value]);
        }
    }

    public static function ensureHeld(User $user, Role $role): void
    {
        if (!$user->roles()->contains($role)) {
            throw new RuleViolation('roles.notHeld', ['name' => $user->profile()->displayName->value]);
        }
    }
}
