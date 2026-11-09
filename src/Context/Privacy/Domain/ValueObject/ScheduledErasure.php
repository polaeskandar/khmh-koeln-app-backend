<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\ValueObject;

use DateTimeImmutable;

final readonly class ScheduledErasure
{
    public function __construct(
        public DateTimeImmutable $receivedAt,
        public string $erasesOn,
    ) {
    }
}
