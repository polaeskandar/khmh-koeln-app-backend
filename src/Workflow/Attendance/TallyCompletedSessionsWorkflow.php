<?php

declare(strict_types=1);

namespace App\Workflow\Attendance;

use App\Context\Meetings\Application\Service\AttendanceMarks;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\People\Application\Service\Attendees;
use App\Workflow\Attendance\Model\SessionTallies;
use App\Workflow\Attendance\Model\TalliedSessions;
use Symfony\Component\Uid\Uuid;

final readonly class TallyCompletedSessionsWorkflow
{
    public function __construct(
        private MeetingSessions $sessions,
        private Attendees $attendees,
        private AttendanceMarks $marks,
    ) {
    }

    /**
     * @param positive-int $limit
     */
    public function __invoke(Uuid $stageId, int $limit): TalliedSessions
    {
        $sessions = $this->sessions->latestCompletedOfStage($stageId, $limit);
        $schedules = $this->sessions->schedulesOf($sessions);

        return new TalliedSessions($sessions, SessionTallies::of(
            $this->attendees->rostersOn(SessionTallies::rosterKeysOf($sessions, $schedules)),
            $this->marks->presentBySession(SessionTallies::sessionIdsOf($sessions)),
            $sessions,
            $schedules,
        ));
    }
}
