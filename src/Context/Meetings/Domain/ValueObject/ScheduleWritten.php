<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Schedule;

final readonly class ScheduleWritten
{
    public function __construct(
        public Schedule $schedule,
        public int $createdSessions,
    ) {
    }
}
