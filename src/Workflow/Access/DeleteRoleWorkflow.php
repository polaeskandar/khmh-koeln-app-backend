<?php

declare(strict_types=1);

namespace App\Workflow\Access;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Application\Service\RoleWrites;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Library\Problem\Model\Ids;

final readonly class DeleteRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RoleWrites $definitions,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Roles, refusal: 'roles.adminOnly');

        $this->definitions->remove(Ids::toUuid($id, notFound: 'role'));
    }
}
