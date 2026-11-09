<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\RoleAssignments;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Workflow\Identity\Model\RoleSummary;

final readonly class GiveRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RoleAssignments $assignments,
    ) {
    }

    public function __invoke(string $accountId, string $roleId): RoleSummary
    {
        $this->access->require(Action::Update, Resource::Roles, refusal: 'roles.adminOnly');

        return RoleSummary::of($this->assignments->give($accountId, $roleId));
    }
}
