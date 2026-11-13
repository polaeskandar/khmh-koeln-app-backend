<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\People\Application\Service\Attendees;
use App\Context\Scheduling\Application\Service\SessionLookup;
use App\Context\Scheduling\Domain\Query\AttendanceQueryInterface;
use App\Workflow\Scheduling\Model\CountedSession;
use App\Workflow\Scheduling\Model\CountedSessions;
use App\Workflow\Scheduling\Model\OverviewSessions;
use App\Workflow\Scheduling\Model\SessionTallies;

final readonly class ShowAttendanceCountsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionLookup $sessions,
        private Attendees $attendees,
        private AttendanceQueryInterface $marks,
    ) {
    }

    /**
     * @param list<string> $sessionIds
     *
     * @return list<CountedSession>
     */
    public function __invoke(array $sessionIds): array
    {
        $scope = $this->access->requireScope(Action::Read, Resource::Attendance);

        $ids = CountedSessions::idsOf($sessionIds);
        $sessions = $this->sessions->listByIds($ids, $scope);
        $schedules = OverviewSessions::schedulesOf($sessions);

        $tallies = SessionTallies::of(
            $this->attendees->listRostersOn(SessionTallies::rosterKeysOf($sessions, $schedules)),
            $this->marks->listPresentBySession(SessionTallies::sessionIdsOf($sessions)),
            $sessions,
            $schedules,
        );

        return CountedSessions::of($ids, $tallies);
    }
}
