<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

final readonly class ApprovalAssignmentRequest
{
    public function __construct(
        public ?string $serviceId = null,
        public ?string $stageId = null,
    ) {
    }
}
