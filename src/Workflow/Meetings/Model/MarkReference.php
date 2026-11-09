<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\AttendanceMark;
use DateTimeImmutable;

final readonly class MarkReference
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $recordedAt,
    ) {
    }

    public static function of(AttendanceMark $mark): self
    {
        return new self($mark->id()->toRfc4122(), $mark->recordedAt());
    }
}
