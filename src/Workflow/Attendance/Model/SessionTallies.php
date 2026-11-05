<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Attendance\Domain\ValueObject\SessionCounts;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\People\Application\Service\Attendees;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class SessionTallies
{
    /**
     * @param ReadableCollection<string, list<Uuid>> $rosters
     * @param ReadableCollection<int, Attendance> $marks
     */
    private function __construct(
        private ReadableCollection $rosters,
        private ReadableCollection $marks,
    ) {
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<non-empty-string, SessionTally>
     */
    public static function lookedUpIn(
        MeetingSessions $meetings,
        Attendees $attendees,
        AttendanceMarks $marks,
        ReadableCollection $sessions,
    ): ReadableCollection {
        $meetings->loadSchedulesOf($sessions);

        $tallies = new self(
            $attendees->rostersOn(
                $sessions
                    ->filter(self::isExpected(...))
                    ->map(self::keyOf(...))
                    ->getValues(),
            ),
            $marks->currentOf($sessions->map(static fn (Session $session): Uuid => $session->id())->getValues()),
        );

        return Collections::firstPerKey($sessions, keyOf: self::idOf(...))->map($tallies->tallyOf(...));
    }

    private function tallyOf(Session $session): SessionTally
    {
        $presentIds = $this->marks
            ->filter(static fn (Attendance $mark): bool => $mark->sessionId()->equals($session->id()))
            ->map(static fn (Attendance $mark): string => self::text($mark->personId()))
            ->getValues();

        if (!self::isExpected($session)) {
            return new SessionTally([], $presentIds, SessionCounts::open($presentIds));
        }

        $rosterIds = new ArrayCollection($this->rosters->get(self::keyOf($session)->id()) ?? [])->map(
            static fn (Uuid $id): string => $id->toRfc4122(),
        )->getValues();

        return new SessionTally($rosterIds, $presentIds, SessionCounts::expected($rosterIds, $presentIds));
    }

    /**
     * @return non-empty-string
     */
    private static function idOf(Session $session): string
    {
        return $session->id()->toRfc4122();
    }

    private static function isExpected(Session $session): bool
    {
        return $session->schedule()->roster()->mode === RosterMode::Expected;
    }

    private static function keyOf(Session $session): RosterKey
    {
        $roster = $session->schedule()->roster();

        return new RosterKey($roster->serviceId, $roster->stageId, $session->occurrenceDate());
    }

    /**
     * @return non-empty-string
     */
    private static function text(Uuid $id): string
    {
        return $id->toRfc4122();
    }
}
