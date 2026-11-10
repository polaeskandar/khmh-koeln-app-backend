<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\LastAdministrator;
use App\Context\Identity\Application\Service\PermissionCatalog;
use App\Context\Identity\Application\Service\RoleCatalog;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Library\Problem\Model\Ids;
use App\Workflow\Identity\Model\RoleDetails;

final readonly class ShowRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RoleCatalog $catalog,
        private LastAdministrator $lastAdministrator,
    ) {
    }

    public function __invoke(string $id): RoleDetails
    {
        $this->access->require(Action::Read, Resource::Roles, refusal: 'roles.adminOnly');

        $role = $this->catalog->find(Ids::toUuid($id, notFound: 'role'));

        return RoleDetails::of(
            $role,
            PermissionCatalog::grantsOf($role),
            $this->catalog->holdersOf($role),
            onlyWayToChangeRoles: $this->lastAdministrator->isKeptOnlyBy($role),
        );
    }
}
