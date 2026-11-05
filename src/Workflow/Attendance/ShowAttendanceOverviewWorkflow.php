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
use App\Workflow\Attendance\Model\AttendanceOverview;
use App\Workflow\Attendance\Model\OverviewCards;
use App\Workflow\Meetings\Model\SessionAbilities;
use Doctrine\Common\Collections\ReadableCollection;

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
        $can = SessionAbilities::grantedBy($this->access);
        $cards = new OverviewCards($this->sessions, $this->attendees, $this->marks, $this->accounts, $this->calendar);
        $earlier = $this->sessions->openBetween($today->addDays(-self::EARLIER_DAYS), $today->addDays(-1));

        return AttendanceOverview::of(
            $today->toString(),
            $cards->ordered($this->sessions->on($today), $this->structure, $can),
            $records ? $cards->ordered($earlier, $this->structure, $can) : [],
            $records ? $cards->unordered($this->closed(), $this->structure, $can) : [],
        );
    }

    /**
     * @return ReadableCollection<int, Session>
     */
    private function closed(): ReadableCollection
    {
        return $this->sessions->latestClosed(self::CLOSED, $this->structure->archivedIds());
    }
}
