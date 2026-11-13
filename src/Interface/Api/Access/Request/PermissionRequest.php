<?php

declare(strict_types=1);

namespace App\Interface\Api\Access\Request;

final readonly class PermissionRequest
{
    public function __construct(
        public ?string $action = null,
        public ?string $resource = null,
    ) {
    }
}
