<?php

declare(strict_types=1);

namespace App\Workflow\Access;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Application\Service\LastAdministrator;
use App\Context\Access\Application\Service\PermissionCatalog;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\Query\RoleHolderQueryInterface;
use App\Context\Access\Domain\Query\RoleQueryInterface;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Model\Ids;
use App\Workflow\Access\Model\RoleDetails;

final readonly class ShowRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RoleQueryInterface $roles,
        private RoleHolderQueryInterface $holders,
        private LastAdministrator $lastAdministrator,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $id): RoleDetails
    {
        $this->access->require(Action::Read, Resource::Roles, refusal: 'roles.adminOnly');

        $role = $this->roles->find(Ids::toUuid($id, notFound: 'role')) ?? throw NotFound::of('role');

        return RoleDetails::of(
            $role,
            PermissionCatalog::grantsOf($role),
            $this->accounts->namedByIds($this->holders->listHoldersOf($role)),
            onlyWayToChangeRoles: $this->lastAdministrator->isKeptOnlyByRole($role),
        );
    }
}
