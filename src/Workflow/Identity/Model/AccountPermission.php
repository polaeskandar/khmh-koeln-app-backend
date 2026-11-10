<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Enum\RoleScope;
use App\Context\Identity\Domain\ValueObject\Grant;

final readonly class AccountPermission
{
    public function __construct(
        public Action $action,
        public Resource $resource,
        public RoleScope $scope,
    ) {
    }

    public static function of(Grant $grant): self
    {
        return new self($grant->action, $grant->resource, $grant->scope);
    }
}
