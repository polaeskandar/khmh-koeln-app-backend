<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Privacy\Domain\ValueObject\ScheduledErasure;
use DateTimeImmutable;

final readonly class ScheduledOwnErasure
{
    public function __construct(
        public DateTimeImmutable $receivedAt,
        public string $erasesOn,
    ) {
    }

    public static function of(ScheduledErasure $scheduled): self
    {
        return new self($scheduled->receivedAt, $scheduled->erasesOn);
    }
}
