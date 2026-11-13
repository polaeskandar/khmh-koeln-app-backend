<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Library\Time\Model\LocalDate;

final readonly class PlannedMeeting
{
    public function __construct(
        public string $date,
        public string $startTime,
        public string $title,
        public string $createdOn,
    ) {
    }

    public static function of(Schedule $schedule, LocalDate $date): self
    {
        return new self(
            date: $date->toString(),
            startTime: $schedule->timeSlot()->startTime->toString(),
            title: $schedule->details()->name,
            createdOn: $date->addDays(-1)->toString(),
        );
    }
}
