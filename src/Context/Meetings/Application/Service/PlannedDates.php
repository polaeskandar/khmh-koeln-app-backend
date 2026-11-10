<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\AgendaDates;
use App\Context\Meetings\Domain\ValueObject\AgendaRange;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class PlannedDates
{
    public function __construct(
        private SessionRepositoryInterface $sessions,
    ) {
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     *
     * @return list<PlannedDate>
     */
    public function listBetween(
        ReadableCollection $schedules,
        ArchivedStructure $archived,
        LocalDate $from,
        LocalDate $to,
    ): array {
        $sessions = $this->sessions->ofSchedulesBetween($schedules, $from, $to)->map(PlannedDate::ofSession(...));
        $planned = self::missing(GeneratingSchedules::among($schedules, $archived), $sessions, $from, $to);

        return Collections::sortedBy(
            new ArrayCollection([...$sessions->getValues(), ...$planned]),
            keyOf: static fn (PlannedDate $date): string => $date->sortKey(),
        );
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    public function getAgendaOf(
        ReadableCollection $schedules,
        ArchivedStructure $archived,
        AgendaRange $range,
    ): AgendaDates {
        $sessions = $this->sessions->ofSchedulesBetween($schedules, $range->from, $range->to);

        return new AgendaDates(
            $schedules,
            $sessions,
            self::missing(
                GeneratingSchedules::among($schedules, $archived),
                $sessions->map(PlannedDate::ofSession(...)),
                $range->plannedFrom(),
                $range->to,
            ),
        );
    }

    /**
     * @param ReadableCollection<int, Schedule> $generating
     * @param ReadableCollection<int, PlannedDate> $held
     *
     * @return list<PlannedDate>
     */
    private static function missing(
        ReadableCollection $generating,
        ReadableCollection $held,
        LocalDate $from,
        LocalDate $to,
    ): array {
        $taken = $held->map(static fn (PlannedDate $date): string => $date->key());

        return Collections::of(self::plannedOf($generating, $taken, $from, $to))->getValues();
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     * @param ReadableCollection<int, string> $taken
     *
     * @return iterable<int, PlannedDate>
     */
    private static function plannedOf(
        ReadableCollection $schedules,
        ReadableCollection $taken,
        LocalDate $from,
        LocalDate $to,
    ): iterable {
        foreach ($schedules as $schedule) {
            foreach ($schedule->recurrence()->occurrencesBetween($from, $to) as $date) {
                $planned = PlannedDate::planned($schedule, $date);

                if ($taken->contains($planned->key())) {
                    continue;
                }

                yield $planned;
            }
        }
    }
}
