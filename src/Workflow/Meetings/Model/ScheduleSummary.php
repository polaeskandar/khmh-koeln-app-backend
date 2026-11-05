<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\ValueObject\ScheduleOutlook;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

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

    public static function of(
        ScheduleOutlook $outlook,
        RosterReferences $references,
        RosterSizes $sizes,
        bool $canManage,
    ): self {
        $schedule = $outlook->schedule;

        return new self(
            ScheduleDetails::of($schedule),
            ScheduleRoster::of($schedule, $references),
            ScheduleTiming::of($schedule),
            ScheduleActivity::of($outlook, $sizes->of($schedule)),
            ScheduleRecord::of($schedule, $canManage),
        );
    }

    /**
     * @param list<ScheduleOutlook> $outlooks
     *
     * @return list<self>
     */
    public static function listOf(
        array $outlooks,
        RosterReferences $references,
        RosterSizes $sizes,
        bool $canManage,
    ): array {
        return new ArrayCollection($outlooks)->map(
            static fn (ScheduleOutlook $outlook): self => self::of($outlook, $references, $sizes, $canManage),
        )->getValues();
    }

    /**
     * @param list<ScheduleOutlook> $outlooks
     *
     * @return ReadableCollection<int, Schedule>
     */
    public static function schedulesOf(array $outlooks): ReadableCollection
    {
        return new ArrayCollection(
            $outlooks,
        )->map(static fn (ScheduleOutlook $outlook): Schedule => $outlook->schedule);
    }
}
