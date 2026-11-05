<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\PermissionGrant;

final readonly class PermissionResponse
{
    public function __construct(
        public string $action,
        public string $resource,
    ) {
    }

    public static function of(PermissionGrant $grant): self
    {
        return new self($grant->action, $grant->resource);
    }
}
