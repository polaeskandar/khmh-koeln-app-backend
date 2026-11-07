<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Domain\Entity\Attendance;
use DateTimeImmutable;

final readonly class MarkReference
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $recordedAt,
    ) {
    }

    public static function of(Attendance $mark): self
    {
        return new self($mark->id()->toRfc4122(), $mark->recordedAt());
    }
}
