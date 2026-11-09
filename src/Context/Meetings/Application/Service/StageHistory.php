<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\AttendanceMarkRepositoryInterface;
use App\Context\Meetings\Domain\Repository\RecentSessionRepositoryInterface;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\MarkedSessions;
use Symfony\Component\Uid\Uuid;

final readonly class StageHistory
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private RecentSessionRepositoryInterface $recent,
        private AttendanceMarkRepositoryInterface $marks,
    ) {
    }

    /**
     * @param positive-int $limit
     */
    public function latestCompleted(Uuid $stageId, int $limit): MarkedSessions
    {
        $schedules = $this->schedules->listed(serviceId: null, stageId: $stageId);
        $sessions = $this->recent->latestCompleted($schedules, $limit);
        $ids = $sessions->map(static fn (Session $session): Uuid => $session->id())->getValues();

        return new MarkedSessions($sessions, $schedules, $this->marks->presentBySession($ids));
    }
}
