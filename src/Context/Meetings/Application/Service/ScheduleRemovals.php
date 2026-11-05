<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\Repository\StructureUsageRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\ScheduleUse;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ScheduleRemovals
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private SessionRepositoryInterface $sessions,
        private StructureUsageRepositoryInterface $usage,
    ) {
    }

    /**
     * @param callable(Uuid): int $lessonsOf
     */
    public function remove(string $id, callable $lessonsOf): void
    {
        $scheduleId = Uuid::isValid($id) ? Uuid::fromString($id) : throw NotFound::of('schedule');

        $this->schedules->withWriteLock($scheduleId, function (Schedule $schedule) use ($lessonsOf): void {
            if ($schedule->isOneOff()) {
                throw NotFound::of('schedule');
            }

            new ScheduleUse(
                sessions: $this->sessionsOf($schedule),
                lessons: $lessonsOf($schedule->id()),
            )->ensureUnused();

            $this->schedules->remove($schedule);
        });
    }

    public function releaseOfService(Uuid $serviceId): int
    {
        $this->usage->lockSchedulesOf($serviceId, stageId: null);

        return $this->released($this->schedules->listed($serviceId, stageId: null));
    }

    public function releaseOfStage(Uuid $stageId): int
    {
        $this->usage->lockSchedulesOf(serviceId: null, stageId: $stageId);

        return $this->released($this->schedules->listed(serviceId: null, stageId: $stageId));
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    private function released(ReadableCollection $schedules): int
    {
        $sessions = $this->sessions->countsBySchedule($schedules)->reduce(
            static fn (int $sum, int $count): int => $sum + $count,
            0,
        );

        if ($sessions === 0) {
            foreach ($schedules as $schedule) {
                $this->schedules->remove($schedule);
            }
        }

        return $sessions;
    }

    private function sessionsOf(Schedule $schedule): int
    {
        $counts = $this->sessions->countsBySchedule(new ArrayCollection([$schedule]));

        return $counts->get($schedule->id()->toRfc4122()) ?? 0;
    }
}
