<?php

declare(strict_types=1);

namespace App\Interface\Api\Access\Response;

use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Workflow\Access\Model\PermissionGrant;

final readonly class PermissionResponse
{
    public function __construct(
        public Action $action,
        public Resource $resource,
    ) {
    }

    public static function of(PermissionGrant $grant): self
    {
        return new self($grant->action, $grant->resource);
    }
}
