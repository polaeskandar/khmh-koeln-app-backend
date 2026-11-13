<?php

declare(strict_types=1);

namespace App\Workflow\Access;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Application\Service\RoleWrites;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;

final readonly class CreateRoleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RoleWrites $definitions,
    ) {
    }

    /**
     * @param list<array{action: string|null, resource: string|null}> $permissions
     */
    public function __invoke(string $name, ?string $scope, array $permissions): string
    {
        $this->access->require(Action::Create, Resource::Roles, refusal: 'roles.adminOnly');

        return $this->definitions->create($name, $scope, $permissions)->id()->toRfc4122();
    }
}
