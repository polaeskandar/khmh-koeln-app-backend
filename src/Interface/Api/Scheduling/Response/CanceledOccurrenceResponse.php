<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Response;

use App\Workflow\Scheduling\Model\CanceledOccurrence;

final readonly class CanceledOccurrenceResponse
{
    public function __construct(
        public string $sessionId,
    ) {
    }

    public static function of(CanceledOccurrence $canceled): self
    {
        return new self($canceled->sessionId);
    }
}
