<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\ValueObject\ArchivedStructure;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class GeneratingSchedules
{
    /**
     * @param ReadableCollection<int, Schedule> $schedules
     *
     * @return ReadableCollection<int, Schedule>
     */
    public static function among(ReadableCollection $schedules, ArchivedStructure $archived): ReadableCollection
    {
        return $schedules->filter(static fn (Schedule $schedule): bool => self::generates($schedule, $archived));
    }

    public static function generates(Schedule $schedule, ArchivedStructure $archived): bool
    {
        return $archived->generates($schedule);
    }
}
