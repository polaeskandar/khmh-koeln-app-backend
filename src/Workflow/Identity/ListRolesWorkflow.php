<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\PermissionCatalog;
use App\Context\Identity\Application\Service\RoleCatalog;
use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Workflow\Identity\Model\RoleList;
use App\Workflow\Identity\Model\RoleRow;

final readonly class ListRolesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RoleCatalog $catalog,
    ) {
    }

    public function __invoke(): RoleList
    {
        $this->access->require(Action::Read, Resource::Roles, refusal: 'roles.adminOnly');

        $counts = $this->catalog->holderCounts();

        return new RoleList(
            $this->catalog
                ->byName()
                ->map(static fn (Role $role): RoleRow => RoleRow::of(
                    $role,
                    PermissionCatalog::grantsOf($role),
                    $counts[$role->id()->toRfc4122()] ?? 0,
                ))
                ->getValues(),
        );
    }
}
