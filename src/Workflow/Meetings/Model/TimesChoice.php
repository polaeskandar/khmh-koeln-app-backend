<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\ValueObject\TimesDraft;

final readonly class TimesChoice
{
    public function __construct(
        public ?string $startTime,
        public ?string $endTime,
        public bool $endTimeSent,
    ) {
    }

    public function draft(): TimesDraft
    {
        return TimesDraft::of($this->startTime, $this->endTime);
    }

    public function change(): TimesDraft
    {
        return TimesDraft::change($this->startTime, $this->endTime, $this->endTimeSent);
    }
}
