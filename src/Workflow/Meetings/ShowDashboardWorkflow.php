<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\ValueObject\AgendaDates;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Library\Time\Client\Calendar;
use App\Workflow\Attendance\Model\OverviewCards;
use App\Workflow\Attendance\Model\SessionTallies;
use App\Workflow\Meetings\Model\Agenda;
use App\Workflow\Meetings\Model\AgendaItem;
use App\Workflow\Meetings\Model\AgendaViews;
use App\Workflow\Meetings\Model\Dashboard;
use App\Workflow\Meetings\Model\RosterReferences;
use App\Workflow\Meetings\Model\SessionAbilities;

final readonly class ShowDashboardWorkflow
{
    private const int DAYS_AHEAD = 7;

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

    public function __invoke(): Dashboard
    {
        $this->access->require(Action::Read, Resource::Services);

        $today = $this->calendar->today();
        $can = SessionAbilities::grantedBy($this->access);
        $cards = new OverviewCards($this->sessions, $this->attendees, $this->marks, $this->accounts, $this->calendar);
        $archived = ArchivedStructure::of($this->structure->archivedIds());

        return new Dashboard(
            $today->toString(),
            $cards->ordered($this->sessions->on($today), $this->structure, $can),
            $this->upcoming($this->sessions->upcoming($today, self::DAYS_AHEAD, $archived), $can),
        );
    }

    /**
     * @return list<AgendaItem>
     */
    private function upcoming(AgendaDates $dates, SessionAbilities $can): array
    {
        return Agenda::itemsOf(
            $dates,
            new AgendaViews(
                RosterReferences::lookedUpIn($this->structure, $dates->schedules),
                $this->calendar,
                $can,
                SessionTallies::ofOpened($this->attendees, $this->marks, $dates->sessions),
            ),
        );
    }
}
