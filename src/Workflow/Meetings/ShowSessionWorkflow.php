<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Time\Client\Calendar;
use App\Workflow\Meetings\Model\ActorReferences;
use App\Workflow\Meetings\Model\HistoryEntry;
use App\Workflow\Meetings\Model\ScheduleBrief;
use App\Workflow\Meetings\Model\SessionAbilities;
use App\Workflow\Meetings\Model\SessionDetail;
use App\Workflow\Meetings\Model\SessionView;

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
        $can = SessionAbilities::grantedBy($this->access);

        return new SessionDetail(
            today: $this->calendar->today()->toString(),
            session: SessionView::of($session, $this->structure, $this->calendar, $can),
            schedule: ScheduleBrief::of($session->schedule()),
            history: HistoryEntry::listOf($events, ActorReferences::lookedUpIn($this->accounts, $events), $can),
        );
    }
}
