<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\SessionLookup;
use App\Context\Meetings\Domain\Query\AttendanceQueryInterface;
use App\Context\People\Application\Service\Attendees;
use App\Workflow\Meetings\Model\CountedSession;
use App\Workflow\Meetings\Model\CountedSessions;
use App\Workflow\Meetings\Model\OverviewSessions;
use App\Workflow\Meetings\Model\SessionTallies;

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
        $scope = $this->access->scope(Action::Read, Resource::Attendance);

        $ids = CountedSessions::idsOf($sessionIds);
        $sessions = $this->sessions->listByIds($ids, $scope);
        $schedules = OverviewSessions::schedulesOf($sessions);

        $tallies = SessionTallies::of(
            $this->attendees->rostersOn(SessionTallies::rosterKeysOf($sessions, $schedules)),
            $this->marks->listPresentBySession(SessionTallies::sessionIdsOf($sessions)),
            $sessions,
            $schedules,
        );

        return CountedSessions::of($ids, $tallies);
    }
}
