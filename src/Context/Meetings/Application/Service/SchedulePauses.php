<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\StructureUsageRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class SchedulePauses
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private StructureUsageRepositoryInterface $usage,
    ) {
    }

    public function pauseService(Uuid $serviceId): bool
    {
        return $this->paused($this->schedules->listed($serviceId, stageId: null));
    }

    public function pauseStage(Uuid $stageId): bool
    {
        return $this->paused($this->schedules->listed(serviceId: null, stageId: $stageId));
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    private function paused(ReadableCollection $schedules): bool
    {
        $ids = $schedules->map(static fn (Schedule $schedule): Uuid => $schedule->id())->getValues();

        if (new ArrayCollection($this->usage->lockUnfinished($ids))->contains('active')) {
            return false;
        }

        foreach ($schedules as $schedule) {
            $schedule->deactivate();
            $this->schedules->save($schedule);
        }

        return true;
    }
}
