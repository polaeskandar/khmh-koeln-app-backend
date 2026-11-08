<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\AttendanceMarks;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\People\Application\Service\Attendees;
use App\Workflow\Meetings\Model\CountedSession;
use App\Workflow\Meetings\Model\CountedSessions;
use App\Workflow\Meetings\Model\OverviewSessions;
use App\Workflow\Meetings\Model\SessionTallies;

final readonly class ShowAttendanceCountsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSessions $sessions,
        private Attendees $attendees,
        private AttendanceMarks $marks,
    ) {
    }

    /**
     * @param list<string> $sessionIds
     *
     * @return list<CountedSession>
     */
    public function __invoke(array $sessionIds): array
    {
        $this->access->require(Action::Read, Resource::Attendance);

        $ids = CountedSessions::idsOf($sessionIds);
        $sessions = $this->sessions->byIds($ids);
        $schedules = OverviewSessions::schedulesOf($sessions);

        $tallies = SessionTallies::of(
            $this->attendees->rostersOn(SessionTallies::rosterKeysOf($sessions, $schedules)),
            $this->marks->presentBySession(SessionTallies::sessionIdsOf($sessions)),
            $sessions,
            $schedules,
        );

        return CountedSessions::of($ids, $tallies);
    }
}
