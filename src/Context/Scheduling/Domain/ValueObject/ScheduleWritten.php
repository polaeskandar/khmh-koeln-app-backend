<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Entity\Schedule;

final readonly class ScheduleWritten
{
    public function __construct(
        public Schedule $schedule,
        public int $createdSessions,
    ) {
    }
}
