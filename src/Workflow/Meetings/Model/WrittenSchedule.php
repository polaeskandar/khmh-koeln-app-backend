<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\ValueObject\ScheduleWritten;

final readonly class WrittenSchedule
{
    public function __construct(
        public string $id,
        public int $createdSessions,
    ) {
    }

    public static function of(ScheduleWritten $written): self
    {
        return new self($written->schedule->id()->toRfc4122(), $written->createdSessions);
    }
}
