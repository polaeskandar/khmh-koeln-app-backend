<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\StageHistory;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\MarkedSessions;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\Attendees;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Workflow\Meetings\Model\AttendanceHistory;
use App\Workflow\Meetings\Model\HistoryRow;
use App\Workflow\Meetings\Model\HistoryRows;
use App\Workflow\Meetings\Model\HistorySession;
use App\Workflow\Meetings\Model\SessionTallies;
use App\Workflow\Meetings\Model\TalliedSessions;
use App\Workflow\Organization\Model\StructureGroup;

final readonly class ShowStageAttendanceHistoryWorkflow
{
    private const int SESSIONS = 8;

    public function __construct(
        private AccessCheck $access,
        private StructureLookup $structure,
        private Attendees $attendees,
        private Calendar $calendar,
        private StageHistory $history,
    ) {
    }

    public function __invoke(string $stageId): AttendanceHistory
    {
        $this->access->require(Action::Read, Resource::Members);

        $stage = $this->structure->stage(Ids::toUuid($stageId, notFound: 'stage'));
        $this->access->requireIn(Action::Read, Resource::Members, StructureGroup::ofStage($stage), missing: 'stage');
        $today = $this->calendar->today();
        $tallied = $this->talliedOf($this->history->latestCompleted($stage->id(), self::SESSIONS));

        return new AttendanceHistory(
            $today->toString(),
            $tallied
                ->sessions
                ->map(fn (Session $session): HistorySession => $this->sessionOf($tallied, $session))
                ->getValues(),
            $this->rowsOf($stage, $today, $tallied),
        );
    }

    private function talliedOf(MarkedSessions $marked): TalliedSessions
    {
        return new TalliedSessions(
            $marked->sessions,
            SessionTallies::of(
                $this->attendees->rostersOn(SessionTallies::rosterKeysOf($marked->sessions, $marked->schedules)),
                $marked->present,
                $marked->sessions,
                $marked->schedules,
            ),
        );
    }

    private function sessionOf(TalliedSessions $tallied, Session $session): HistorySession
    {
        $date = $session->occurrenceDate();

        return new HistorySession(
            $session->id()->toRfc4122(),
            $date->toString(),
            $this->calendar->instantAt($date, $session->plan()->timeSlot->startTime),
            $tallied->tallyOf($session)->counts->present,
        );
    }

    /**
     * @return list<HistoryRow>
     */
    private function rowsOf(Stage $stage, LocalDate $today, TalliedSessions $tallied): array
    {
        $key = new RosterKey($stage->service()->id(), $stage->id(), $today);
        $members = $this->attendees->peopleOf($this->attendees->rostersOn([$key])->get($key->id()) ?? []);

        return HistoryRows::of($members, $tallied->inOrder());
    }
}
