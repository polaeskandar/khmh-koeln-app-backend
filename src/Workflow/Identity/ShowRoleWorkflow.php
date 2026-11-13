<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\LastAdministrator;
use App\Context\Identity\Application\Service\PermissionCatalog;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Query\RoleHolderQueryInterface;
use App\Context\Identity\Domain\Query\RoleQueryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Model\Ids;
use App\Workflow\Identity\Model\RoleDetails;

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
            $this->accounts->named($this->holders->listHoldersOf($role)),
            onlyWayToChangeRoles: $this->lastAdministrator->isKeptOnlyByRole($role),
        );
    }
}
