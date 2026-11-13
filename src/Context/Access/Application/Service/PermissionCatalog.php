<?php

declare(strict_types=1);

namespace App\Context\Access\Application\Service;

use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\ValueObject\Access;
use App\Library\Collections\Model\Collections;

final readonly class PermissionCatalog
{
    /**
     * @return list<Access>
     */
    public static function grantsOf(Role $role): array
    {
        return Collections::of(
            self::everyAccess(),
        )->filter(static fn (Access $access): bool => RolePermissions::isGrantedToRole(
            $role,
            $access->action,
            $access->resource,
        ))->getValues();
    }

    /**
     * @return iterable<int, Access>
     */
    private static function everyAccess(): iterable
    {
        foreach (Resource::cases() as $resource) {
            foreach (Action::cases() as $action) {
                yield new Access($action, $resource);
            }
        }
    }
}
