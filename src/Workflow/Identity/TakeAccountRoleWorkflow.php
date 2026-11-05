<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountRoles;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class TakeAccountRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountRoles $roles,
    ) {
    }

    public function __invoke(string $accountId, string $roleId, string $actorId): void
    {
        $this->access->require(Action::Update, Resource::Roles, refusal: 'roles.adminOnly');

        $this->roles->take($accountId, $roleId, $actorId);
    }
}
