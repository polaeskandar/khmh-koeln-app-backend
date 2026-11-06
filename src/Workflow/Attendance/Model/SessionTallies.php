<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Attendance\Domain\ValueObject\SessionCounts;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\RosterMode;
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
     * @param ReadableCollection<string, list<Uuid>> $rosters
     * @param ReadableCollection<int, Attendance> $marks
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<non-empty-string, SessionTally>
     */
    public static function of(
        ReadableCollection $rosters,
        ReadableCollection $marks,
        ReadableCollection $sessions,
    ): ReadableCollection {
        $tallies = new self($rosters, $marks);

        return Collections::firstPerKey($sessions, keyOf: self::idOf(...))->map($tallies->tallyOf(...));
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return list<Uuid>
     */
    public static function scheduleIdsOf(ReadableCollection $sessions): array
    {
        return Collections::firstPerKey(
            $sessions->map(static fn (Session $session): Uuid => $session->schedule()->id()),
            keyOf: static fn (Uuid $id): string => $id->toRfc4122(),
        )->getValues();
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return list<RosterKey>
     */
    public static function rosterKeysOf(ReadableCollection $sessions): array
    {
        return $sessions
            ->filter(self::isExpected(...))
            ->map(self::keyOf(...))
            ->getValues();
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return list<Uuid>
     */
    public static function sessionIdsOf(ReadableCollection $sessions): array
    {
        return $sessions->map(static fn (Session $session): Uuid => $session->id())->getValues();
    }

    private function tallyOf(Session $session): SessionTally
    {
        $presentIds = $this->marks
            ->filter(static fn (Attendance $mark): bool => $mark->sessionId()->equals($session->id()))
            ->map(static fn (Attendance $mark): string => $mark->personId()->toRfc4122())
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
}
