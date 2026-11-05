<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\Occurrences;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Context\Meetings\Domain\ValueObject\ScheduleOutlook;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;

final readonly class ScheduleOutlooks
{
    private const int NEXT_DATES = 3;

    public function __construct(
        private PlannedDates $plannedDates,
        private SessionRepositoryInterface $sessions,
    ) {
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     *
     * @return list<ScheduleOutlook>
     */
    public function of(ReadableCollection $schedules, ArchivedStructure $archived, LocalDate $today): array
    {
        $generating = $archived
            ->generating($schedules)
            ->filter(static fn (Schedule $schedule): bool => !$schedule->recurrence()->span->endsBefore($today));

        $upcoming = $this->upcoming($generating, $archived, $today);
        $counts = $this->sessions->countsBySchedule($schedules);

        return $schedules->map(
            static fn (Schedule $schedule): ScheduleOutlook => new ScheduleOutlook(
                $schedule,
                generating: $generating->contains($schedule),
                nextDates: self::nextDatesOf($schedule, $upcoming),
                sessionCount: $counts->get($schedule->id()->toRfc4122()) ?? 0,
            ),
        )->getValues();
    }

    public function one(Schedule $schedule, ArchivedStructure $archived, LocalDate $today): ScheduleOutlook
    {
        $outlook = new ArrayCollection($this->of(new ArrayCollection([$schedule]), $archived, $today))->first();

        return $outlook instanceof ScheduleOutlook ? $outlook : throw new LogicException('A schedule has an outlook.');
    }

    /**
     * @param ReadableCollection<int, Schedule> $generating
     *
     * @return ReadableCollection<int, PlannedDate>
     */
    private function upcoming(
        ReadableCollection $generating,
        ArchivedStructure $archived,
        LocalDate $today,
    ): ReadableCollection {
        $horizon = $today->addDays(Occurrences::HORIZON_DAYS);

        return new ArrayCollection($this->plannedDates->between($generating, $archived, $today, $horizon))->filter(
            static fn (PlannedDate $date): bool => $date->isUpcoming(),
        );
    }

    /**
     * @param ReadableCollection<int, PlannedDate> $upcoming
     *
     * @return list<PlannedDate>
     */
    private static function nextDatesOf(Schedule $schedule, ReadableCollection $upcoming): array
    {
        $own = $upcoming->filter(static fn (PlannedDate $date): bool => $date->schedule === $schedule)->getValues();

        return new ArrayCollection(new ArrayCollection($own)->slice(offset: 0, length: self::NEXT_DATES))->getValues();
    }
}
