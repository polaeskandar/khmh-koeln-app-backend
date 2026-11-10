<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\RoleDefinitions;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Library\Problem\Model\Ids;

final readonly class DeleteRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RoleDefinitions $definitions,
    ) {
    }

    public function __invoke(string $id): void
    {
        $this->access->require(Action::Delete, Resource::Roles, refusal: 'roles.adminOnly');

        $this->definitions->remove(Ids::toUuid($id, notFound: 'role'));
    }
}
