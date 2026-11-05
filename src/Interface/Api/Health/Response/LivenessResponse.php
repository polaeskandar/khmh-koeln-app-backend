<?php

declare(strict_types=1);

namespace App\Interface\Api\Health\Response;

use App\Workflow\Health\Model\Readiness;

final readonly class LivenessResponse
{
    public function __construct(
        public string $status = Readiness::OK,
    ) {
    }
}
