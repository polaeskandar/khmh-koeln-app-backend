<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Workflow\Identity\Model\PermissionGrant;

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
