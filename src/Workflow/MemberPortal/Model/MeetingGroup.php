<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Meetings\Domain\ValueObject\MeetingDetails;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Workflow\Organization\Model\ServiceReference;

final readonly class MeetingGroup
{
    public function __construct(
        public string $title,
        public ?string $location,
        public string $group,
        public ServiceReference $service,
    ) {
    }

    public static function of(PlannedDate $date, MeetingDetails $details, MemberRoster $roster): self
    {
        $schedule = $date->schedule;

        return new self(
            $details->name,
            $details->location,
            $roster->groupOf($schedule),
            ServiceReference::of($roster->groups->service($schedule->roster()->serviceId)),
        );
    }
}
