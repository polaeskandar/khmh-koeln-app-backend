<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\PermissionCatalog;
use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Query\RoleHolderQueryInterface;
use App\Context\Identity\Domain\Query\RoleQueryInterface;
use App\Workflow\Identity\Model\RoleList;
use App\Workflow\Identity\Model\RoleRow;

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
