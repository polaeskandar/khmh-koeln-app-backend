<?php

declare(strict_types=1);

namespace App\Platform\Health\Response;

use App\Platform\Health\Model\Readiness;

final readonly class LivenessResponse
{
    public function __construct(
        public string $status = Readiness::OK,
    ) {
    }
}
