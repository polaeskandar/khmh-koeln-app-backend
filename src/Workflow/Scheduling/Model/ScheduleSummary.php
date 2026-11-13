<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\ValueObject\ScheduleOutlook;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ScheduleSummary
{
    public function __construct(
        public ScheduleDetails $details,
        public ScheduleRoster $roster,
        public ScheduleTiming $timing,
        public ScheduleActivity $activity,
        public ScheduleRecord $record,
    ) {
    }

    public static function of(ScheduleOutlook $outlook, RosterReferences $references, RosterSizes $sizes): self
    {
        $schedule = $outlook->schedule;

        return new self(
            ScheduleDetails::of($schedule),
            ScheduleRoster::of($schedule, $references),
            ScheduleTiming::of($schedule),
            ScheduleActivity::of($outlook, $sizes->sizeOf($schedule)),
            ScheduleRecord::of($schedule),
        );
    }

    /**
     * @param list<ScheduleOutlook> $outlooks
     *
     * @return list<self>
     */
    public static function listOf(array $outlooks, RosterReferences $references, RosterSizes $sizes): array
    {
        return new ArrayCollection($outlooks)->map(
            static fn (ScheduleOutlook $outlook): self => self::of($outlook, $references, $sizes),
        )->getValues();
    }
}
