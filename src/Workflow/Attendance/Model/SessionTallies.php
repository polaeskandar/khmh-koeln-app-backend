<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Attendance\Domain\ValueObject\ErasedAttendance;
use App\Context\Attendance\Domain\ValueObject\SessionCounts;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\People\Application\Service\Attendees;
use App\Context\People\Application\Service\Rosters;
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

        return self::counted($attendees->rostersOn(self::rosterKeysOf($sessions)), $marks, $sessions);
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<non-empty-string, SessionTally>
     */
    public static function ofOpened(
        Attendees $attendees,
        AttendanceMarks $marks,
        ReadableCollection $sessions,
    ): ReadableCollection {
        $opened = $sessions->filter(static fn (Session $session): bool => $session->progress()->wasOpened());

        return self::counted($attendees->rostersOn(self::rosterKeysOf($opened)), $marks, $opened);
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<non-empty-string, SessionTally>
     */
    public static function onRosters(
        Rosters $rosters,
        AttendanceMarks $marks,
        ReadableCollection $sessions,
    ): ReadableCollection {
        return self::counted($rosters->rostersOn(self::rosterKeysOf($sessions)), $marks, $sessions);
    }

    private function tallyOf(Session $session): SessionTally
    {
        $presentIds = $this->marks
            ->filter(static fn (Attendance $mark): bool => $mark->sessionId()->equals($session->id()))
            ->map(static fn (Attendance $mark): string => self::text($mark->personId()))
            ->getValues();

        if (!SessionRoster::isExpected($session)) {
            return new SessionTally([], $presentIds, SessionCounts::open($presentIds, self::erasedOf($session)));
        }

        $rosterIds = new ArrayCollection($this->rosters->get(SessionRoster::keyOf($session)->id()) ?? [])->map(
            static fn (Uuid $id): string => $id->toRfc4122(),
        )->getValues();

        return new SessionTally(
            $rosterIds,
            $presentIds,
            SessionCounts::expected($rosterIds, $presentIds, self::erasedOf($session)),
        );
    }

    /**
     * @param ReadableCollection<string, list<Uuid>> $rosters
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<non-empty-string, SessionTally>
     */
    private static function counted(
        ReadableCollection $rosters,
        AttendanceMarks $marks,
        ReadableCollection $sessions,
    ): ReadableCollection {
        $tallies = new self(
            $rosters,
            $marks->currentOf($sessions->map(static fn (Session $session): Uuid => $session->id())->getValues()),
        );

        return Collections::firstPerKey($sessions, keyOf: self::idOf(...))->map($tallies->tallyOf(...));
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return list<RosterKey>
     */
    private static function rosterKeysOf(ReadableCollection $sessions): array
    {
        return $sessions
            ->filter(SessionRoster::isExpected(...))
            ->map(SessionRoster::keyOf(...))
            ->getValues();
    }

    private static function erasedOf(Session $session): ErasedAttendance
    {
        $erased = $session->erased();

        return new ErasedAttendance($erased->roster, $erased->present, $erased->rosterPresent);
    }

    /**
     * @return non-empty-string
     */
    private static function idOf(Session $session): string
    {
        return $session->id()->toRfc4122();
    }

    /**
     * @return non-empty-string
     */
    private static function text(Uuid $id): string
    {
        return $id->toRfc4122();
    }
}
