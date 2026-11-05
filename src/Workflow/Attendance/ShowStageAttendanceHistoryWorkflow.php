<?php

declare(strict_types=1);

namespace App\Workflow\Attendance;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\Attendees;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Client\Calendar;
use App\Workflow\Attendance\Model\AttendanceHistory;
use App\Workflow\Attendance\Model\HistoryRow;
use App\Workflow\Attendance\Model\HistorySession;
use App\Workflow\Attendance\Model\SessionTallies;
use App\Workflow\Attendance\Model\SessionTally;
use App\Workflow\Meetings\Model\SessionTimes;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;

final readonly class ShowStageAttendanceHistoryWorkflow
{
    private const int SESSIONS = 8;

    // @mago-expect lint:excessive-parameter-list
    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private MeetingSessions $sessions,
        private Attendees $attendees,
        private AttendanceMarks $marks,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $stageId): AttendanceHistory
    {
        $this->access->require(Action::Read, Resource::Members);

        $stage = $this->structure->stage($stageId);
        $sessions = $this->sessions->latestCompletedOfStage($stage->id(), self::SESSIONS);
        $tallies = SessionTallies::lookedUpIn($this->sessions, $this->attendees, $this->marks, $sessions);
        $ordered = $sessions->map(static fn (Session $session): SessionTally => self::tallyOf($tallies, $session));

        return new AttendanceHistory(
            $this->calendar->today()->toString(),
            $sessions->map(
                fn (Session $session): HistorySession => new HistorySession(
                    $session->id()->toRfc4122(),
                    $session->occurrenceDate()->toString(),
                    SessionTimes::of($session, $this->calendar)->startsAt,
                    self::tallyOf($tallies, $session)->counts->present,
                ),
            )->getValues(),
            $this->rowsOf($stage, $ordered->getValues()),
        );
    }

    /**
     * @param list<SessionTally> $tallies
     *
     * @return list<HistoryRow>
     */
    private function rowsOf(Stage $stage, array $tallies): array
    {
        $key = new RosterKey($stage->service()->id(), $stage->id(), $this->calendar->today());
        $members = $this->attendees->peopleOf($this->attendees->rostersOn([$key])->get($key->id()) ?? []);

        return new ArrayCollection(Collections::sortedBy($members, keyOf: self::nameKey(...)))->map(
            static fn (Person $person): HistoryRow => HistoryRow::of($person, $tallies),
        )->getValues();
    }

    /**
     * @param ReadableCollection<non-empty-string, SessionTally> $tallies
     */
    private static function tallyOf(ReadableCollection $tallies, Session $session): SessionTally
    {
        return $tallies->get($session->id()->toRfc4122()) ?? throw new LogicException('Every session has a tally.');
    }

    private static function nameKey(Person $person): string
    {
        $name = $person->name();
        $sortable = InputText::of($name->lastName . ' ' . $name->firstName)->lowered()->toString();

        return $sortable . ' ' . $person->id()->toRfc4122();
    }
}
