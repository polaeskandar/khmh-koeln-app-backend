<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\LastAdministrator;
use App\Context\Identity\Application\Service\PermissionCatalog;
use App\Context\Identity\Application\Service\RoleCatalog;
use App\Context\Identity\Application\Service\RoleDefinitions;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Library\Problem\Model\Ids;
use App\Workflow\Identity\Model\RoleDetails;

final readonly class EditRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RoleDefinitions $definitions,
        private RoleCatalog $catalog,
        private LastAdministrator $lastAdministrator,
    ) {
    }

    /**
     * @param list<array{action: string|null, resource: string|null}> $permissions
     */
    public function __invoke(string $id, string $name, ?string $scope, array $permissions): RoleDetails
    {
        $this->access->require(Action::Update, Resource::Roles, refusal: 'roles.adminOnly');

        $roleId = Ids::toUuid($id, notFound: 'role');
        $this->definitions->edit($roleId, $name, $scope, $permissions);
        $role = $this->catalog->get($roleId);

        return RoleDetails::of(
            $role,
            PermissionCatalog::grantsOf($role),
            $this->catalog->holdersOf($role),
            onlyWayToChangeRoles: $this->lastAdministrator->isKeptOnlyByRole($role),
        );
    }
}
