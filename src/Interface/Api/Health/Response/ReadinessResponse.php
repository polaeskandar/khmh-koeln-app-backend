<?php

declare(strict_types=1);

namespace App\Interface\Api\Health\Response;

use App\Workflow\Health\Model\Readiness;

final readonly class ReadinessResponse
{
    /**
     * @param array<string, string> $checks
     */
    public function __construct(
        public string $status,
        public array $checks,
    ) {
    }

    public static function of(Readiness $readiness): self
    {
        return new self($readiness->status, $readiness->checks);
    }
}
