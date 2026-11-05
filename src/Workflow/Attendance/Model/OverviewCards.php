<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\Attendees;
use App\Library\Time\Client\Calendar;
use App\Workflow\Meetings\Model\RosterReferences;
use App\Workflow\Meetings\Model\SessionAbilities;
use App\Workflow\Meetings\Model\SessionView;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class OverviewCards
{
    public function __construct(
        private MeetingSessions $sessions,
        private Attendees $attendees,
        private AttendanceMarks $marks,
        private AccountLookup $accounts,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return list<OverviewCard>
     */
    public function ordered(ReadableCollection $sessions, StructureLookup $structure, SessionAbilities $can): array
    {
        $references = RosterReferences::ofSessions($structure, $sessions);
        $open = OverviewSessions::ordered(OverviewSessions::unarchived($sessions, $references), $references);

        return $this->of($open, $references, $can);
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return list<OverviewCard>
     */
    public function unordered(ReadableCollection $sessions, StructureLookup $structure, SessionAbilities $can): array
    {
        $references = RosterReferences::ofSessions($structure, $sessions);

        return $this->of(OverviewSessions::unarchived($sessions, $references), $references, $can);
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return list<OverviewCard>
     */
    private function of(ReadableCollection $sessions, RosterReferences $references, SessionAbilities $can): array
    {
        $tallies = SessionTallies::lookedUpIn($this->sessions, $this->attendees, $this->marks, $sessions);

        $closers = AccountNames::lookedUpIn(
            $this->accounts,
            $sessions->map(static fn (Session $session): ?Uuid => $session->progress()->closed->by)->getValues(),
        );

        return $sessions->map(fn (Session $session): OverviewCard => OverviewCard::of(
            $session,
            SessionView::with($session, $references, $this->calendar, $can),
            $tallies,
            $closers,
            $this->calendar,
        ))->getValues();
    }
}
