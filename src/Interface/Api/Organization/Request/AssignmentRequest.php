<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization\Request;

final readonly class AssignmentRequest
{
    public function __construct(
        public ?string $serviceId = null,
        public ?string $stageId = null,
    ) {
    }
}
