<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Workflow\Privacy\Model\ScheduledOwnErasure;
use DateTimeImmutable;

final readonly class ScheduledErasureResponse
{
    public function __construct(
        public DateTimeImmutable $receivedAt,
        public string $erasesOn,
    ) {
    }

    public static function of(ScheduledOwnErasure $scheduled): self
    {
        return new self($scheduled->receivedAt, $scheduled->erasesOn);
    }
}
