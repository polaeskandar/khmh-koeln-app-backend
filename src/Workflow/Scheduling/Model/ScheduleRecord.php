<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\Entity\Schedule;
use DateTimeImmutable;

final readonly class ScheduleRecord
{
    public function __construct(
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public static function of(Schedule $schedule): self
    {
        return new self(createdAt: $schedule->createdAt(), updatedAt: $schedule->updatedAt());
    }
}
