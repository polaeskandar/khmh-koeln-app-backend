<?php

declare(strict_types=1);

namespace App\Workflow\Attendance;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Library\Time\Client\Calendar;
use App\Workflow\Attendance\Model\AttendanceBoard;
use App\Workflow\Attendance\Model\AttendanceCounts;
use App\Workflow\Attendance\Model\BoardLists;
use App\Workflow\Attendance\Model\BoardSession;
use App\Workflow\Attendance\Model\MarkReferences;
use App\Workflow\Attendance\Model\SessionTallies;
use App\Workflow\Attendance\Model\SessionTally;
use App\Workflow\Meetings\Model\ActorReferences;
use App\Workflow\Meetings\Model\HistoryEntry;
use App\Workflow\Meetings\Model\SessionAbilities;
use App\Workflow\Meetings\Model\SessionView;
use Doctrine\Common\Collections\ArrayCollection;
use LogicException;

final readonly class ShowAttendanceBoardWorkflow
{
    // @mago-expect lint:excessive-parameter-list
    public function __construct(
        private AccessCheck $access,
        private AccountLookup $accounts,
        private MeetingSessions $sessions,
        private Attendees $attendees,
        private AttendanceMarks $marks,
        private StructureLookup $structure,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $sessionId): AttendanceBoard
    {
        $this->access->require(Action::Read, Resource::Attendance);

        $session = $this->sessions->find($sessionId);
        $can = SessionAbilities::grantedBy($this->access);
        $events = $this->sessions->historyOf($session);
        $view = SessionView::of($session, $this->structure, $this->calendar, $can);
        $tally = $this->tallyOf($session);

        return new AttendanceBoard(
            today: $this->calendar->today()->toString(),
            session: BoardSession::of($session, $view, $this->accounts, $this->calendar),
            counts: AttendanceCounts::of($tally->counts),
            lists: $this->listsOf($session, $tally),
            history: HistoryEntry::listOf($events, ActorReferences::lookedUpIn($this->accounts, $events), $can),
        );
    }

    private function tallyOf(Session $session): SessionTally
    {
        $tallies = SessionTallies::lookedUpIn(
            $this->sessions,
            $this->attendees,
            $this->marks,
            new ArrayCollection([$session]),
        );

        return $tallies->get($session->id()->toRfc4122()) ?? throw new LogicException('Every session has a tally.');
    }

    private function listsOf(Session $session, SessionTally $tally): BoardLists
    {
        if (!BoardLists::areShownFor($session)) {
            return BoardLists::none();
        }

        $marks = $this->marks->ofSession($session->id());

        return BoardLists::lookedUpIn(
            $tally,
            $marks,
            MarkReferences::lookedUpIn($this->accounts, $this->calendar, $marks),
            $this->attendees,
            $this->structure,
        );
    }
}
