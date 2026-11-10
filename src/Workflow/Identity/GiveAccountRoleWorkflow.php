<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountRoles;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Library\Problem\Model\Ids;
use App\Workflow\Identity\Model\RoleSummary;

final readonly class GiveAccountRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountRoles $roles,
    ) {
    }

    public function __invoke(string $accountId, string $roleId): RoleSummary
    {
        $this->access->require(Action::Update, Resource::Roles, refusal: 'roles.adminOnly');

        return RoleSummary::of($this->roles->give(
            Ids::toUuid($accountId, notFound: 'user'),
            Ids::toUuidOrNull($roleId),
        ));
    }
}
