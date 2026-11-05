<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use DateTimeInterface;

final readonly class ScheduleRecord
{
    public function __construct(
        public string $createdAt,
        public string $updatedAt,
        public bool $canManage,
    ) {
    }

    public static function of(Schedule $schedule, bool $canManage): self
    {
        return new self(
            createdAt: $schedule->createdAt()->format(DateTimeInterface::ATOM),
            updatedAt: $schedule->updatedAt()->format(DateTimeInterface::ATOM),
            canManage: $canManage,
        );
    }
}
