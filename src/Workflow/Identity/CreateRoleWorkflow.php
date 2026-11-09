<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\RoleDefinitions;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class CreateRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RoleDefinitions $definitions,
    ) {
    }

    /**
     * @param list<array{action: string|null, resource: string|null}> $permissions
     */
    public function __invoke(string $name, array $permissions): string
    {
        $this->access->require(Action::Create, Resource::Roles, refusal: 'roles.adminOnly');

        return $this->definitions->create($name, $permissions)->id()->toRfc4122();
    }
}
