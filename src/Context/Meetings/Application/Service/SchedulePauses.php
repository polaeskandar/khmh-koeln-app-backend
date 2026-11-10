<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\StructureUsageRepositoryInterface;
use App\Library\Problem\Exception\RuleViolation;
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

    public function pauseService(Uuid $serviceId, string $name): void
    {
        if (!$this->paused($this->schedules->listed($serviceId, stageId: null))) {
            throw new RuleViolation('services.takingAttendance', ['name' => $name]);
        }
    }

    public function pauseStage(Uuid $stageId, string $name): void
    {
        if (!$this->paused($this->schedules->listed(serviceId: null, stageId: $stageId))) {
            throw new RuleViolation('stages.takingAttendance', ['name' => $name]);
        }
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    private function paused(ReadableCollection $schedules): bool
    {
        $ids = $schedules->map(static fn (Schedule $schedule): Uuid => $schedule->id())->getValues();

        if (new ArrayCollection($this->usage->lockUnfinished($ids))->contains(SessionStatus::Active)) {
            return false;
        }

        foreach ($schedules as $schedule) {
            $schedule->deactivate();
            $this->schedules->save($schedule);
        }

        return true;
    }
}
