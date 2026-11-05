<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Workflow\Attendance\Model\SessionTally;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class RecentAttendance
{
    /**
     * @param ReadableCollection<int, Session> $completed
     * @param ReadableCollection<non-empty-string, SessionTally> $tallies
     */
    public function __construct(
        private ReadableCollection $completed,
        private ReadableCollection $tallies,
    ) {
    }

    public function trendOf(string $serviceId): AttendanceTrend
    {
        return AttendanceTrend::of(
            $this->completed->filter(static fn (Session $session): bool => self::serviceIdOf($session) === $serviceId),
            $this->tallies,
        );
    }

    private static function serviceIdOf(Session $session): string
    {
        return $session->schedule()->roster()->serviceId->toRfc4122();
    }
}
