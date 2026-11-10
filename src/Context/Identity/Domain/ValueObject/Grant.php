<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Enum\RoleScope;

final readonly class Grant
{
    public function __construct(
        public Action $action,
        public Resource $resource,
        public RoleScope $scope,
    ) {
    }
}
