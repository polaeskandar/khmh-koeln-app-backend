<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class PermissionGrant
{
    public function __construct(
        public Action $action,
        public Resource $resource,
    ) {
    }
}
