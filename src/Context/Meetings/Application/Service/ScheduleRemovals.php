<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\ScheduleUse;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ScheduleRemovals
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private SessionRepositoryInterface $sessions,
    ) {
    }

    /**
     * @param callable(Uuid): int $lessonsOf
     */
    public function remove(string $id, callable $lessonsOf): void
    {
        $scheduleId = Uuid::isValid($id) ? Uuid::fromString($id) : throw NotFound::of('schedule');

        $this->schedules->withWriteLock($scheduleId, function (Schedule $schedule) use ($lessonsOf): void {
            new ScheduleUse(
                sessions: $this->sessionsOf($schedule),
                lessons: $lessonsOf($schedule->id()),
            )->ensureUnused();

            $this->schedules->remove($schedule);
        });
    }

    private function sessionsOf(Schedule $schedule): int
    {
        $counts = $this->sessions->countsBySchedule(new ArrayCollection([$schedule]));

        return $counts->get($schedule->id()->toRfc4122()) ?? 0;
    }
}
