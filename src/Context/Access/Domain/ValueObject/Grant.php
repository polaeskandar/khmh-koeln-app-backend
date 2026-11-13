<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\ValueObject;

use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\Enum\RoleScope;

final readonly class Grant
{
    public function __construct(
        public Action $action,
        public Resource $resource,
        public RoleScope $scope,
    ) {
    }
}
