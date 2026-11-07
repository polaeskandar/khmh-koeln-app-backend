<?php

declare(strict_types=1);

namespace App\Workflow\Attendance;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\AttendanceMarks;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\Entity\Attendance;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Workflow\Attendance\Model\AttendanceBoard;
use App\Workflow\Attendance\Model\AttendanceCounts;
use App\Workflow\Attendance\Model\BoardLists;
use App\Workflow\Attendance\Model\BoardRows;
use App\Workflow\Attendance\Model\SessionTallies;
use App\Workflow\Attendance\Model\SessionTally;
use App\Workflow\Attendance\Model\VisitorRows;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\Placements;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;

final readonly class ShowAttendanceBoardWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSessions $sessions,
        private Attendees $attendees,
        private AttendanceMarks $marks,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $sessionId): AttendanceBoard
    {
        $this->access->require(Action::Read, Resource::Attendance);

        $session = $this->sessions->find($sessionId);
        $current = $this->marks->currentOf($session->id());
        $tally = $this->tallyOf($session, $current);
        $counts = AttendanceCounts::of($tally->counts);

        return BoardLists::areShownFor($session)
            ? $this->boardOf($counts, $tally, $current)
            : AttendanceBoard::withoutLists($counts);
    }

    /**
     * @param ReadableCollection<int, Attendance> $current
     */
    private function tallyOf(Session $session, ReadableCollection $current): SessionTally
    {
        $sessions = new ArrayCollection([$session]);
        $schedules = $this->sessions->schedulesOf($sessions);
        $rosters = $this->attendees->rostersOn(SessionTallies::rosterKeysOf($sessions, $schedules));
        $tallies = SessionTallies::of($rosters, BoardLists::presentOf($current), $sessions, $schedules);

        return $tallies->get($session->id()->toRfc4122()) ?? throw new LogicException('Every session has a tally.');
    }

    /**
     * @param ReadableCollection<int, Attendance> $current
     */
    private function boardOf(
        AttendanceCounts $counts,
        SessionTally $tally,
        ReadableCollection $current,
    ): AttendanceBoard {
        $byPerson = BoardLists::byPerson($current);
        $visitors = BoardLists::visitorsOf($tally, $byPerson);
        $people = $this->attendees->peopleOf(BoardLists::personIdsOf($tally, $current));
        $memberships = $this->attendees->currentMembershipsOf(BoardLists::peopleOf($visitors, $people));

        $placements = Placements::of(
            $this->structure->services(DirectoryEntries::serviceIdsOf($memberships)),
            $this->structure->stages(DirectoryEntries::stageIdsOf($memberships)),
        );

        $byName = $this->attendees->byName(BoardLists::visitorIdsOf($visitors));

        return new AttendanceBoard(
            $counts,
            BoardRows::roster($tally->rosterIds, $people, $byPerson),
            VisitorRows::of($visitors, $byName, $people, $memberships, $placements),
            BoardRows::recent($current, $people),
        );
    }
}
