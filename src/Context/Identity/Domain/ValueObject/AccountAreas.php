<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class AccountAreas
{
    public const string STAFF = 'STAFF_AREA';

    public const string PARENT_PORTAL = 'PARENT_PORTAL';

    private function __construct(
        private bool $staff,
        private bool $parentPortal,
    ) {
    }

    public static function of(User $user): self
    {
        if (!$user->status()->isActive()) {
            return new self(staff: false, parentPortal: false);
        }

        return new self(
            staff: !$user
                ->roles()
                ->filter(self::grantsStaffWork(...))
                ->isEmpty(),
            parentPortal: $user->can(Action::Read, Resource::ParentPortal),
        );
    }

    public function opens(string $area): bool
    {
        $staff = $area === self::STAFF && $this->staff;

        return $staff || $area === self::PARENT_PORTAL && $this->parentPortal;
    }

    private static function grantsStaffWork(Role $role): bool
    {
        return !$role
            ->permissions()
            ->filter(self::isStaffPermission(...))
            ->isEmpty();
    }

    private static function isStaffPermission(Permission $permission): bool
    {
        return $permission->resource() !== Resource::ParentPortal;
    }
}
