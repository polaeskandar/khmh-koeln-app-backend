<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\RoleAssignments;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class TakeRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RoleAssignments $assignments,
    ) {
    }

    public function __invoke(string $accountId, string $roleId, string $actorId): void
    {
        $this->access->require(Action::Update, Resource::Roles, refusal: 'roles.adminOnly');

        $this->assignments->take($accountId, $roleId, $actorId);
    }
}
