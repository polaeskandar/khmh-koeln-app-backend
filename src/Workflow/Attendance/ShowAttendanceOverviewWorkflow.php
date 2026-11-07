<?php

declare(strict_types=1);

namespace App\Workflow\Attendance;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Workflow\Attendance\Model\AttendanceOverview;
use App\Workflow\Attendance\Model\OverviewCard;
use App\Workflow\Attendance\Model\OverviewSessions;
use App\Workflow\Identity\Model\AccountReferences;
use App\Workflow\Meetings\Model\RosterReferences;
use App\Workflow\Meetings\Model\SessionState;
use App\Workflow\Meetings\Model\SessionView;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ShowAttendanceOverviewWorkflow
{
    private const int EARLIER_DAYS = 21;

    private const int CLOSED = 6;

    public function __construct(
        private AccessCheck $access,
        private MeetingSessions $sessions,
        private StructureLookup $structure,
        private AccountLookup $accounts,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(): AttendanceOverview
    {
        $this->access->require(Action::Read, Resource::Attendance);

        $today = $this->calendar->today();
        $todays = $this->sessions->on($today);
        $earlier = $this->earlierThan($today);
        $closed = $this->recentlyClosed();
        $all = new ArrayCollection([...$todays->getValues(), ...$earlier->getValues(), ...$closed->getValues()]);
        $references = $this->referencesOf($all);
        $closers = AccountReferences::of($this->accounts->accounts(OverviewSessions::closerIdsOf($all)));
        $cardOf = fn (Session $session): OverviewCard => $this->cardOf($session, $references, $closers);

        return AttendanceOverview::of(
            $today->toString(),
            OverviewSessions::ordered(OverviewSessions::unarchived($todays, $references), $references)->map(
                $cardOf,
            )->getValues(),
            OverviewSessions::ordered(OverviewSessions::unarchived($earlier, $references), $references)->map(
                $cardOf,
            )->getValues(),
            OverviewSessions::unarchived($closed, $references)->map($cardOf)->getValues(),
        );
    }

    /**
     * @return ReadableCollection<int, Session>
     */
    private function earlierThan(LocalDate $today): ReadableCollection
    {
        return $this->access->allows(Action::Create, Resource::Attendance)
            ? $this->sessions->openBetween($today->addDays(-self::EARLIER_DAYS), $today->addDays(-1))
            : new ArrayCollection();
    }

    /**
     * @return ReadableCollection<int, Session>
     */
    private function recentlyClosed(): ReadableCollection
    {
        return $this->access->allows(Action::Create, Resource::Attendance)
            ? $this->sessions->latestClosed(self::CLOSED, $this->structure->archivedIds())
            : new ArrayCollection();
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     */
    private function referencesOf(ReadableCollection $sessions): RosterReferences
    {
        $schedules = OverviewSessions::schedulesOf($sessions);

        return RosterReferences::of(
            $this->structure->services(RosterReferences::serviceIdsOf($schedules)),
            $this->structure->stages(RosterReferences::stageIdsOf($schedules)),
        );
    }

    private function cardOf(Session $session, RosterReferences $references, AccountReferences $closers): OverviewCard
    {
        $slot = $session->schedule()->timeSlot();
        $date = $session->occurrenceDate();
        $endTime = $slot->endTime;
        $endsAt = $endTime === null ? null : $this->calendar->instantAt($date, $endTime);

        return new OverviewCard(
            SessionView::of($session, $references, $this->calendar->instantAt($date, $slot->startTime), $endsAt),
            SessionState::referencing($session, $closers->referenceOf($session->progress()->closed->by)),
        );
    }
}
