<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
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
    public function between(
        ReadableCollection $schedules,
        ArchivedStructure $archived,
        LocalDate $from,
        LocalDate $to,
    ): array {
        $sessions = $this->sessions->ofSchedulesBetween($schedules, $from, $to)->map(PlannedDate::ofSession(...));
        $taken = $sessions->map(static fn (PlannedDate $date): string => $date->key());
        $planned = Collections::of(self::plannedOf($archived->generating($schedules), $taken, $from, $to));

        return Collections::sortedBy(
            new ArrayCollection([...$sessions->getValues(), ...$planned->getValues()]),
            keyOf: static fn (PlannedDate $date): string => $date->sortKey(),
        );
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
