<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Repository\AttendanceRepositoryInterface;
use App\Context\Scheduling\Domain\Repository\RecentSessionRepositoryInterface;
use App\Context\Scheduling\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\MarkedSessions;
use Symfony\Component\Uid\Uuid;

final readonly class StageHistory
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private RecentSessionRepositoryInterface $recent,
        private AttendanceRepositoryInterface $attendance,
    ) {
    }

    /**
     * @param positive-int $limit
     */
    public function listLatestCompleted(Uuid $stageId, int $limit): MarkedSessions
    {
        $schedules = $this->schedules->listed(serviceId: null, stageId: $stageId);
        $sessions = $this->recent->latestCompleted($schedules, $limit);
        $ids = $sessions->map(static fn (Session $session): Uuid => $session->id())->getValues();

        return new MarkedSessions($sessions, $schedules, $this->attendance->listPresentBySession($ids));
    }
}
