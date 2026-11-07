<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Time\Client\Calendar;
use App\Workflow\Meetings\Model\ActorReferences;
use App\Workflow\Meetings\Model\HistoryEntry;
use App\Workflow\Meetings\Model\RosterReferences;
use App\Workflow\Meetings\Model\ScheduleBrief;
use App\Workflow\Meetings\Model\ScheduleRoster;
use App\Workflow\Meetings\Model\SessionDetail;
use App\Workflow\Meetings\Model\SessionDetails;
use App\Workflow\Meetings\Model\SessionProgress;
use App\Workflow\Meetings\Model\SessionState;
use App\Workflow\Meetings\Model\SessionTimes;
use App\Workflow\Meetings\Model\SessionView;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ShowSessionWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSessions $sessions,
        private AccountLookup $accounts,
        private StructureLookup $structure,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $id): SessionDetail
    {
        $this->access->require(Action::Read, Resource::Services);

        $session = $this->sessions->find($id);
        $events = $this->sessions->historyOf($session);

        $history = new ArrayCollection(HistoryEntry::listOf(
            $events,
            ActorReferences::of($this->accounts->accounts(ActorReferences::actorIdsOf($events))),
        ));

        $progress = SessionProgress::of($session);

        if (!$this->access->allows(Action::Read, Resource::Attendance)) {
            $history = $history->map(static fn (HistoryEntry $entry): HistoryEntry => $entry->withoutNote());
            $progress = $progress->withoutCancelReason();
        }

        return new SessionDetail(
            today: $this->calendar->today()->toString(),
            session: $this->viewOf($session, $progress),
            state: SessionState::of($session, $this->accounts->account($session->progress()->closed->by)),
            schedule: ScheduleBrief::of($session->schedule()),
            history: $history->getValues(),
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
                $this->structure->services(RosterReferences::serviceIdsOf($schedules)),
                $this->structure->stages(RosterReferences::stageIdsOf($schedules)),
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
