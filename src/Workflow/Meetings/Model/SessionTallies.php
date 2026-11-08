<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\Meetings\Domain\ValueObject\SessionCounts;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class SessionTallies
{
    /**
     * @var ReadableCollection<non-empty-string, Schedule>
     */
    private ReadableCollection $schedules;

    /**
     * @param ReadableCollection<string, list<Uuid>> $rosters
     * @param ReadableCollection<string, list<string>> $present
     * @param ReadableCollection<int, Schedule> $schedules
     */
    private function __construct(
        private ReadableCollection $rosters,
        private ReadableCollection $present,
        ReadableCollection $schedules,
    ) {
        $keyOf = static fn (Schedule $schedule): string => $schedule->id()->toRfc4122();
        $this->schedules = Collections::firstPerKey($schedules, keyOf: $keyOf);
    }

    /**
     * @param ReadableCollection<string, list<Uuid>> $rosters
     * @param ReadableCollection<string, list<string>> $present
     * @param ReadableCollection<int, Session> $sessions
     * @param ReadableCollection<int, Schedule> $schedules
     *
     * @return ReadableCollection<non-empty-string, SessionTally>
     */
    public static function of(
        ReadableCollection $rosters,
        ReadableCollection $present,
        ReadableCollection $sessions,
        ReadableCollection $schedules,
    ): ReadableCollection {
        $tallies = new self($rosters, $present, $schedules);

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
     * @param ReadableCollection<int, Schedule> $schedules
     *
     * @return list<RosterKey>
     */
    public static function rosterKeysOf(ReadableCollection $sessions, ReadableCollection $schedules): array
    {
        $tallies = new self(new ArrayCollection(), new ArrayCollection(), $schedules);

        return $sessions
            ->filter($tallies->isExpected(...))
            ->map($tallies->keyOf(...))
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
        $presentIds = $this->present->get(self::idOf($session)) ?? [];

        if (!$this->isExpected($session)) {
            return new SessionTally([], $presentIds, SessionCounts::open($presentIds, $session->erased()));
        }

        $rosterIds = new ArrayCollection($this->rosters->get($this->keyOf($session)->id()) ?? [])->map(
            static fn (Uuid $id): string => $id->toRfc4122(),
        )->getValues();

        return new SessionTally(
            $rosterIds,
            $presentIds,
            SessionCounts::expected($rosterIds, $presentIds, $session->erased()),
        );
    }

    /**
     * @return non-empty-string
     */
    private static function idOf(Session $session): string
    {
        return $session->id()->toRfc4122();
    }

    private function isExpected(Session $session): bool
    {
        return $session->plan()->rosterMode === RosterMode::Expected;
    }

    private function keyOf(Session $session): RosterKey
    {
        $roster = $this->scheduleOf($session)->roster();

        return new RosterKey($roster->serviceId, $roster->stageId, $session->occurrenceDate());
    }

    private function scheduleOf(Session $session): Schedule
    {
        $schedule = $this->schedules->get($session->schedule()->id()->toRfc4122());

        return $schedule ?? throw new LogicException('The session\'s schedule was not looked up.');
    }
}
