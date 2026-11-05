<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\People\Application\Service\Attendees;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class RosterSizes
{
    /**
     * @param ReadableCollection<string, list<Uuid>> $rosters
     */
    private function __construct(
        private ReadableCollection $rosters,
        private LocalDate $today,
    ) {
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    public static function lookedUpIn(Attendees $attendees, ReadableCollection $schedules, LocalDate $today): self
    {
        $keys = $schedules
            ->filter(self::isExpected(...))
            ->map(static fn (Schedule $schedule): RosterKey => self::keyOn($schedule, $today))
            ->getValues();

        return new self($attendees->rostersOn($keys), $today);
    }

    public function of(Schedule $schedule): ?int
    {
        if (!self::isExpected($schedule)) {
            return null;
        }

        return new ArrayCollection($this->rosters->get(self::keyOn($schedule, $this->today)->id()) ?? [])->count();
    }

    private static function isExpected(Schedule $schedule): bool
    {
        return $schedule->roster()->mode === RosterMode::Expected;
    }

    private static function keyOn(Schedule $schedule, LocalDate $date): RosterKey
    {
        $roster = $schedule->roster();

        return new RosterKey($roster->serviceId, $roster->stageId, $date);
    }
}
