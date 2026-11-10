<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\CoveredSchedules;
use App\Context\Meetings\Application\Service\SessionLookup;
use App\Context\Meetings\Domain\Entity\Attendance;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Query\AttendanceQueryInterface;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Library\Problem\Model\Ids;
use App\Workflow\Meetings\Model\AttendanceBoard;
use App\Workflow\Meetings\Model\AttendanceCounts;
use App\Workflow\Meetings\Model\BoardLists;
use App\Workflow\Meetings\Model\BoardRows;
use App\Workflow\Meetings\Model\SessionTallies;
use App\Workflow\Meetings\Model\SessionTally;
use App\Workflow\Meetings\Model\VisitorRows;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\Placements;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;

final readonly class ShowAttendanceBoardWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionLookup $sessions,
        private Attendees $attendees,
        private AttendanceQueryInterface $marks,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $sessionId): AttendanceBoard
    {
        $this->access->require(Action::Read, Resource::Attendance);

        $session = $this->sessions->get(Ids::toUuid($sessionId, notFound: 'session'));
        $group = CoveredSchedules::groupOfSession($session);
        $this->access->requireIn(Action::Read, Resource::Attendance, $group, missing: 'session');
        $current = $this->marks->listCurrentMarksOf($session->id());
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
        $schedules = $this->sessions->listSchedulesOf($sessions);
        $rosters = $this->attendees->listRostersOn(SessionTallies::rosterKeysOf($sessions, $schedules));
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
        $people = $this->attendees->listPeopleOf(BoardLists::personIdsOf($tally, $current));
        $memberships = $this->attendees->listCurrentMembershipsOf(BoardLists::peopleOf($visitors, $people));

        $placements = Placements::of(
            $this->structure->listServices(DirectoryEntries::serviceIdsOf($memberships)),
            $this->structure->listStages(DirectoryEntries::stageIdsOf($memberships)),
        );

        $byName = $this->attendees->listByName(BoardLists::visitorIdsOf($visitors));

        return new AttendanceBoard(
            $counts,
            BoardRows::roster($tally->rosterIds, $people, $byPerson),
            VisitorRows::of($visitors, $byName, $people, $memberships, $placements),
            BoardRows::recent($current, $people),
        );
    }
}
