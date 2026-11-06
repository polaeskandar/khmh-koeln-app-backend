<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageReference;

final readonly class ScheduleRoster
{
    public function __construct(
        public ?ServiceReference $service,
        public ?StageReference $stage,
        public string $mode,
    ) {
    }

    public static function of(Schedule $schedule, RosterReferences $references): self
    {
        return new self(
            service: $references->serviceOf($schedule),
            stage: $references->stageOf($schedule),
            mode: $schedule->roster()->mode->value,
        );
    }
}
