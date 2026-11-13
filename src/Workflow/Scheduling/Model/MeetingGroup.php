<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\ValueObject\PlannedDate;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\People\Model\MemberRoster;

final readonly class MeetingGroup
{
    public function __construct(
        public string $title,
        public ?string $location,
        public string $group,
        public ServiceReference $service,
    ) {
    }

    public static function of(PlannedDate $date, MemberRoster $roster): self
    {
        $schedule = $date->schedule;

        return new self(
            $date->plan->title,
            $date->plan->location,
            $roster->groupOf($schedule),
            ServiceReference::of($roster->groups->service($schedule->roster()->serviceId)),
        );
    }
}
