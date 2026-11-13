<?php

declare(strict_types=1);

namespace App\Workflow\Access;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Application\Service\PermissionCatalog;
use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\Query\RoleHolderQueryInterface;
use App\Context\Access\Domain\Query\RoleQueryInterface;
use App\Workflow\Access\Model\RoleList;
use App\Workflow\Access\Model\RoleRow;

final readonly class ListRolesWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RoleQueryInterface $roles,
        private RoleHolderQueryInterface $holders,
    ) {
    }

    public function __invoke(): RoleList
    {
        $this->access->require(Action::Read, Resource::Roles, refusal: 'roles.adminOnly');

        $counts = $this->holders->countHolders();

        return new RoleList(
            $this->roles
                ->listByName()
                ->map(static fn (Role $role): RoleRow => RoleRow::of(
                    $role,
                    PermissionCatalog::grantsOf($role),
                    $counts[$role->id()->toRfc4122()] ?? 0,
                ))
                ->getValues(),
        );
    }
}
