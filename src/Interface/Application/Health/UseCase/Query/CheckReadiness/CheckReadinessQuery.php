<?php

declare(strict_types=1);

namespace App\Interface\Application\Health\UseCase\Query\CheckReadiness;

final readonly class CheckReadinessQuery
{
    /**
     * @param positive-int $timeoutSeconds
     */
    public function __construct(
        public int $timeoutSeconds = 2,
    ) {
    }
}
