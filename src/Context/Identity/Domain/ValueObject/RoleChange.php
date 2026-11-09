<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Entity\Role;
use App\Library\Problem\Exception\RuleViolation;

final readonly class RoleChange
{
    public static function ensureGivable(Account $account, Role $role): void
    {
        if ($account->status()->isPending()) {
            throw new RuleViolation('roles.accountPending');
        }

        if ($account->roles()->contains($role)) {
            throw new RuleViolation('roles.alreadyHasRole', ['name' => $account->profile()->displayName->value]);
        }
    }

    public static function ensureHeld(Account $account, Role $role): void
    {
        if (!$account->roles()->contains($role)) {
            throw new RuleViolation('roles.notHeld', ['name' => $account->profile()->displayName->value]);
        }
    }
}
