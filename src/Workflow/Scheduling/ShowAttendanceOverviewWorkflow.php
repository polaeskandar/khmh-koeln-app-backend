<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Scheduling\Application\Service\SessionLookup;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\ValueObject\ArchivedStructure;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Workflow\Identity\Model\AccountReferences;
use App\Workflow\Scheduling\Model\AttendanceOverview;
use App\Workflow\Scheduling\Model\OverviewCard;
use App\Workflow\Scheduling\Model\OverviewSessions;
use App\Workflow\Scheduling\Model\RosterReferences;
use App\Workflow\Scheduling\Model\SessionState;
use App\Workflow\Scheduling\Model\SessionView;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ShowAttendanceOverviewWorkflow
{
    private const int EARLIER_DAYS = 21;

    private const int CLOSED = 6;

    public function __construct(
        private AccessCheck $access,
        private SessionLookup $sessions,
        private StructureLookup $structure,
        private AccountLookup $accounts,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(): AttendanceOverview
    {
        $scope = $this->access->requireScope(Action::Read, Resource::Attendance);

        $today = $this->calendar->today();
        $archivedIds = $this->structure->archivedIds();
        $archived = ArchivedStructure::of($archivedIds);
        $todays = OverviewSessions::unarchived($this->sessions->listOn($today, $scope), $archived);
        $earlier = OverviewSessions::unarchived($this->earlierThan($today), $archived);
        $closed = OverviewSessions::unarchived($this->recentlyClosed($archivedIds), $archived);
        $all = new ArrayCollection([...$todays->getValues(), ...$earlier->getValues(), ...$closed->getValues()]);
        $references = $this->referencesOf($all);
        $closers = AccountReferences::of($this->accounts->namedByIds(OverviewSessions::closerIdsOf($all)));
        $cardOf = fn (Session $session): OverviewCard => $this->cardOf($session, $references, $closers);

        return AttendanceOverview::of(
            $today->toString(),
            OverviewSessions::ordered($todays, $references)->map($cardOf)->getValues(),
            OverviewSessions::ordered($earlier, $references)->map($cardOf)->getValues(),
            $closed->map($cardOf)->getValues(),
        );
    }

    /**
     * @return ReadableCollection<int, Session>
     */
    private function earlierThan(LocalDate $today): ReadableCollection
    {
        return $this->sessions->listOpenBetween(
            $today->addDays(-self::EARLIER_DAYS),
            $today->addDays(-1),
            $this->access->getScope(Action::Create, Resource::Attendance),
        );
    }

    /**
     * @param list<Uuid> $archivedIds
     *
     * @return ReadableCollection<int, Session>
     */
    private function recentlyClosed(array $archivedIds): ReadableCollection
    {
        return $this->sessions->listLatestClosed(
            self::CLOSED,
            $archivedIds,
            $this->access->getScope(Action::Create, Resource::Attendance),
        );
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     */
    private function referencesOf(ReadableCollection $sessions): RosterReferences
    {
        $schedules = OverviewSessions::schedulesOf($sessions);

        return RosterReferences::of(
            $this->structure->listServices(RosterReferences::serviceIdsOf($schedules)),
            $this->structure->listStages(RosterReferences::stageIdsOf($schedules)),
        );
    }

    private function cardOf(Session $session, RosterReferences $references, AccountReferences $closers): OverviewCard
    {
        $slot = $session->plan()->timeSlot;
        $date = $session->occurrenceDate();
        $endTime = $slot->endTime;
        $endsAt = $endTime === null ? null : $this->calendar->instantAt($date, $endTime);

        return new OverviewCard(
            SessionView::of($session, $references, $this->calendar->instantAt($date, $slot->startTime), $endsAt),
            SessionState::referencing($session, $closers->referenceOf($session->progress()->closed->by)),
        );
    }
}
