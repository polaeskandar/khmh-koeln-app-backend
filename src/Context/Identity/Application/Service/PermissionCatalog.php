<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\Access;
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
        )->filter(static fn (Access $access): bool => RolePermissions::roleAllows(
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
