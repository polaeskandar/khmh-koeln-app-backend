<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\ValueObject\ScheduleOutlook;
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

    public static function of(ScheduleOutlook $outlook, RosterReferences $references, bool $canManage): self
    {
        $schedule = $outlook->schedule;

        return new self(
            ScheduleDetails::of($schedule),
            ScheduleRoster::of($schedule, $references),
            ScheduleTiming::of($schedule),
            ScheduleActivity::of($outlook),
            ScheduleRecord::of($schedule, $canManage),
        );
    }

    /**
     * @param list<ScheduleOutlook> $outlooks
     *
     * @return list<self>
     */
    public static function listOf(array $outlooks, RosterReferences $references, bool $canManage): array
    {
        return new ArrayCollection($outlooks)->map(
            static fn (ScheduleOutlook $outlook): self => self::of($outlook, $references, $canManage),
        )->getValues();
    }
}
