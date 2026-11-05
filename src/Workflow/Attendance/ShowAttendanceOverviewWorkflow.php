<?php

declare(strict_types=1);

namespace App\Workflow\Attendance;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Library\Time\Client\Calendar;
use App\Workflow\Attendance\Model\AccountNames;
use App\Workflow\Attendance\Model\AttendanceOverview;
use App\Workflow\Attendance\Model\OverviewCard;
use App\Workflow\Attendance\Model\OverviewSessions;
use App\Workflow\Attendance\Model\SessionTallies;
use App\Workflow\Meetings\Model\RosterReferences;
use App\Workflow\Meetings\Model\SessionAbilities;
use App\Workflow\Meetings\Model\SessionView;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ShowAttendanceOverviewWorkflow
{
    private const int EARLIER_DAYS = 21;

    private const int CLOSED = 6;

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

    public function __invoke(): AttendanceOverview
    {
        $this->access->require(Action::Read, Resource::Attendance);

        $today = $this->calendar->today();
        $records = $this->access->allows(Action::Create, Resource::Attendance);
        $earlier = $this->sessions->openBetween($today->addDays(-self::EARLIER_DAYS), $today->addDays(-1));

        return AttendanceOverview::of(
            $today->toString(),
            $this->orderedCardsOf($this->sessions->on($today)),
            $records ? $this->orderedCardsOf($earlier) : [],
            $records
                ? $this->cardsOf($this->sessions->latestClosed(self::CLOSED, $this->structure->archivedIds()))
                : [],
        );
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return list<OverviewCard>
     */
    private function orderedCardsOf(ReadableCollection $sessions): array
    {
        $references = $this->referencesOf($sessions);

        return $this->cards(
            OverviewSessions::ordered(OverviewSessions::unarchived($sessions, $references), $references),
            $references,
        );
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return list<OverviewCard>
     */
    private function cardsOf(ReadableCollection $sessions): array
    {
        $references = $this->referencesOf($sessions);

        return $this->cards(OverviewSessions::unarchived($sessions, $references), $references);
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return list<OverviewCard>
     */
    private function cards(ReadableCollection $sessions, RosterReferences $references): array
    {
        $tallies = SessionTallies::lookedUpIn($this->sessions, $this->attendees, $this->marks, $sessions);

        $closers = AccountNames::lookedUpIn(
            $this->accounts,
            $sessions->map(static fn (Session $session): ?Uuid => $session->progress()->closed->by)->getValues(),
        );

        $can = SessionAbilities::grantedBy($this->access);

        return $sessions->map(fn (Session $session): OverviewCard => OverviewCard::of(
            $session,
            SessionView::with($session, $references, $this->calendar, $can),
            $tallies,
            $closers,
            $this->calendar,
        ))->getValues();
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     */
    private function referencesOf(ReadableCollection $sessions): RosterReferences
    {
        return RosterReferences::lookedUpIn($this->structure, $sessions->map(
            static fn (Session $session): Schedule => $session->schedule(),
        ));
    }
}
