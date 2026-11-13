<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\ValueObject\TimesDraft;

final readonly class TimesChoice
{
    public function __construct(
        public ?string $startTime,
        public ?string $endTime,
    ) {
    }

    public function draft(): TimesDraft
    {
        return new TimesDraft($this->startTime, $this->endTime);
    }
}
