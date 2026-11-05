<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Workflow\Meetings\Model\ScheduleTiming;

final readonly class GroupSchedule
{
    public function __construct(
        public string $id,
        public string $name,
        public ScheduleTiming $timing,
        public ?string $location,
    ) {
    }

    public static function of(Schedule $schedule): self
    {
        $details = $schedule->details();

        return new self(
            $schedule->id()->toRfc4122(),
            $details->name,
            ScheduleTiming::of($schedule),
            $details->location,
        );
    }
}
