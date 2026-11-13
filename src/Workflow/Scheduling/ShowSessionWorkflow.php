<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Scheduling\Application\Service\CoveredSchedules;
use App\Context\Scheduling\Application\Service\SessionLookup;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\Scheduling\Model\ActorReferences;
use App\Workflow\Scheduling\Model\HistoryEntry;
use App\Workflow\Scheduling\Model\RosterReferences;
use App\Workflow\Scheduling\Model\ScheduleBrief;
use App\Workflow\Scheduling\Model\ScheduleRoster;
use App\Workflow\Scheduling\Model\SessionDetail;
use App\Workflow\Scheduling\Model\SessionDetails;
use App\Workflow\Scheduling\Model\SessionProgress;
use App\Workflow\Scheduling\Model\SessionState;
use App\Workflow\Scheduling\Model\SessionTimes;
use App\Workflow\Scheduling\Model\SessionView;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ShowSessionWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionLookup $sessions,
        private AccountLookup $accounts,
        private StructureLookup $structure,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $id): SessionDetail
    {
        $this->access->require(Action::Read, Resource::Services);

        $session = $this->sessions->get(Ids::toUuid($id, notFound: 'session'));
        $group = CoveredSchedules::groupOfSession($session);
        $this->access->requireIn(Action::Read, Resource::Services, $group, missing: 'session');
        $history = new ArrayCollection($this->historyOf($session));
        $progress = SessionProgress::of($session);

        if (!$this->access->getScope(Action::Read, Resource::Attendance)->covers($group)) {
            $history = $history->map(static fn (HistoryEntry $entry): HistoryEntry => $entry->withoutNote());
            $progress = $progress->withoutCancelReason();
        }

        return new SessionDetail(
            today: $this->calendar->today()->toString(),
            session: $this->viewOf($session, $progress),
            state: SessionState::of($session, $this->accounts->namedByIds([$session->progress()->closed->by])),
            schedule: ScheduleBrief::ofRecurring($session->schedule()),
            history: $history->getValues(),
        );
    }

    /**
     * @return list<HistoryEntry>
     */
    private function historyOf(Session $session): array
    {
        $events = $this->sessions->listHistoryOf($session);

        return HistoryEntry::listOf(
            $events,
            ActorReferences::of($this->accounts->namedByIds(ActorReferences::actorIdsOf($events))),
        );
    }

    private function viewOf(Session $session, SessionProgress $progress): SessionView
    {
        $schedule = $session->schedule();
        $schedules = new ArrayCollection([$schedule]);
        $slot = $session->plan()->timeSlot;
        $date = $session->occurrenceDate();
        $endTime = $slot->endTime;

        return new SessionView(
            SessionDetails::of($session),
            ScheduleRoster::ofSession($session, RosterReferences::of(
                $this->structure->listServices(RosterReferences::serviceIdsOf($schedules)),
                $this->structure->listStages(RosterReferences::stageIdsOf($schedules)),
            )),
            SessionTimes::of(
                $session,
                startsAt: $this->calendar->instantAt($date, $slot->startTime),
                endsAt: $endTime === null ? null : $this->calendar->instantAt($date, $endTime),
            ),
            $progress,
        );
    }
}
