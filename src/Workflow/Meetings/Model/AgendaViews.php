<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Library\Time\Client\Calendar;
use App\Workflow\Attendance\Model\AttendanceCounts;
use App\Workflow\Attendance\Model\SessionTally;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class AgendaViews
{
    /**
     * @param ReadableCollection<non-empty-string, SessionTally> $tallies
     */
    public function __construct(
        private RosterReferences $references,
        private Calendar $calendar,
        private SessionAbilities $can,
        private ReadableCollection $tallies,
    ) {
    }

    public function sessionItem(Session $session): AgendaItem
    {
        $tally = $this->tallies->get($session->id()->toRfc4122());
        $view = SessionView::with($session, $this->references, $this->calendar, $this->can);

        return AgendaItem::session($view, $tally === null ? null : AttendanceCounts::of($tally->counts));
    }

    public function plannedItem(PlannedDate $date): AgendaItem
    {
        return AgendaItem::planned(PlannedSession::of($date, $this->references, $this->calendar, $this->can));
    }
}
