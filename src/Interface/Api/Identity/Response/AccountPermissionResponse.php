<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Enum\RoleScope;
use App\Workflow\Identity\Model\AccountPermission;

final readonly class AccountPermissionResponse
{
    public function __construct(
        public Action $action,
        public Resource $resource,
        public RoleScope $scope,
    ) {
    }

    public static function of(AccountPermission $permission): self
    {
        return new self($permission->action, $permission->resource, $permission->scope);
    }
}
