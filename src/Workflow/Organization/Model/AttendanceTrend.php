<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Attendance\Domain\ValueObject\SessionCounts;
use App\Context\Meetings\Domain\Entity\Session;
use App\Library\Collections\Model\Collections;
use App\Library\Types\Model\Ratio;
use App\Workflow\Attendance\Model\SessionRoster;
use App\Workflow\Attendance\Model\SessionTally;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class AttendanceTrend
{
    public function __construct(
        public int $sessions,
        public ?float $rate,
        public ?int $averagePresent,
    ) {
    }

    public static function none(): self
    {
        return new self(sessions: 0, rate: null, averagePresent: null);
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     * @param ReadableCollection<non-empty-string, SessionTally> $tallies
     */
    public static function of(ReadableCollection $sessions, ReadableCollection $tallies): self
    {
        $expected = self::countsOf($sessions->filter(SessionRoster::isExpected(...)), $tallies);

        $open = self::countsOf(
            $sessions->filter(static fn (Session $session): bool => !SessionRoster::isExpected($session)),
            $tallies,
        );

        $rosterPresent = $expected->reduce(
            static fn (int $sum, SessionCounts $counts): int => $sum + $counts->rosterPresent,
            0,
        );

        $rosterSize = $expected->reduce(
            static fn (int $sum, SessionCounts $counts): int => $sum + ($counts->rosterSize ?? 0),
            0,
        );

        $present = $open->reduce(static fn (int $sum, SessionCounts $counts): int => $sum + $counts->present, 0);

        return new self(
            $sessions->count(),
            Ratio::of($rosterPresent, whole: $rosterSize),
            Ratio::mean($present, count: $open->count()),
        );
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     * @param ReadableCollection<non-empty-string, SessionTally> $tallies
     *
     * @return ReadableCollection<int, SessionCounts>
     */
    private static function countsOf(ReadableCollection $sessions, ReadableCollection $tallies): ReadableCollection
    {
        return Collections::mapPresent(
            $sessions,
            static fn (Session $session): ?SessionCounts => $tallies->get($session->id()->toRfc4122())?->counts,
        );
    }
}
