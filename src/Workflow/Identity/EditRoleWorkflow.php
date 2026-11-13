<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\LastAdministrator;
use App\Context\Identity\Application\Service\PermissionCatalog;
use App\Context\Identity\Application\Service\RoleWrites;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Query\RoleHolderQueryInterface;
use App\Context\Identity\Domain\Query\RoleQueryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Model\Ids;
use App\Workflow\Identity\Model\RoleDetails;

final readonly class EditRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RoleWrites $definitions,
        private RoleQueryInterface $roles,
        private RoleHolderQueryInterface $holders,
        private LastAdministrator $lastAdministrator,
        private AccountLookup $accounts,
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
        $role = $this->roles->find($roleId) ?? throw NotFound::of('role');

        return RoleDetails::of(
            $role,
            PermissionCatalog::grantsOf($role),
            $this->accounts->named($this->holders->listHoldersOf($role)),
            onlyWayToChangeRoles: $this->lastAdministrator->isKeptOnlyByRole($role),
        );
    }
}
