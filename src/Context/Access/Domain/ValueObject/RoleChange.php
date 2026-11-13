<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\ValueObject;

use App\Context\Access\Domain\Entity\Role;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RoleChange
{
    /**
     * @param list<Role> $held the account's roles
     */
    public static function ensureGivable(array $held, Role $role, string $accountName): void
    {
        if (self::holds($held, $role)) {
            throw new RuleViolation('roles.alreadyHasRole', ['name' => $accountName]);
        }
    }

    /**
     * @param list<Role> $held the account's roles
     */
    public static function ensureHeld(array $held, Role $role, string $accountName): void
    {
        if (!self::holds($held, $role)) {
            throw new RuleViolation('roles.notHeld', ['name' => $accountName]);
        }
    }

    /**
     * @param list<Role> $held
     */
    private static function holds(array $held, Role $role): bool
    {
        return !new ArrayCollection($held)
            ->filter(static fn (Role $one): bool => $one->id()->equals($role->id()))
            ->isEmpty();
    }
}
