<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class PermissionGrant
{
    public function __construct(
        public string $action,
        public string $resource,
    ) {
    }
}
