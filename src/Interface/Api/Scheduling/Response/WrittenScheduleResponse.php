<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Response;

use App\Workflow\Scheduling\Model\WrittenSchedule;

final readonly class WrittenScheduleResponse
{
    public function __construct(
        public string $id,
        public int $createdSessions,
    ) {
    }

    public static function of(WrittenSchedule $written): self
    {
        return new self($written->id, $written->createdSessions);
    }
}
