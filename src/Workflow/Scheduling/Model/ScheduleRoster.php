<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Enum\RosterMode;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageReference;

final readonly class ScheduleRoster
{
    public function __construct(
        public ?ServiceReference $service,
        public ?StageReference $stage,
        public RosterMode $mode,
    ) {
    }

    public static function of(Schedule $schedule, RosterReferences $references): self
    {
        return new self(
            service: $references->serviceOf($schedule),
            stage: $references->stageOf($schedule),
            mode: $schedule->roster()->mode,
        );
    }

    public static function ofSession(Session $session, RosterReferences $references): self
    {
        $schedule = $session->schedule();

        return new self(
            service: $references->serviceOf($schedule),
            stage: $references->stageOf($schedule),
            mode: $session->plan()->rosterMode,
        );
    }
}
