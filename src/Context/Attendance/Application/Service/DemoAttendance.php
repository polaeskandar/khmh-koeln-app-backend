<?php

declare(strict_types=1);

namespace App\Context\Attendance\Application\Service;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Attendance\Domain\Repository\AttendanceRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class DemoAttendance
{
    public function __construct(
        private AttendanceRepositoryInterface $attendance,
    ) {
    }

    /**
     * @param list<Uuid> $people
     */
    public function markAll(Uuid $sessionId, array $people, DateTimeImmutable $from, Uuid $by): void
    {
        foreach ($people as $minute => $personId) {
            $at = $from->modify('+' . $minute . ' minutes');
            $this->attendance->save(Attendance::record($sessionId, $personId, $at, $by));
        }
    }

    public function markRemoved(Uuid $sessionId, Uuid $personId, DateTimeImmutable $at, Uuid $by, string $reason): void
    {
        $mark = Attendance::record($sessionId, $personId, $at, $by);
        $mark->remove($by, $at->modify('+5 minutes'), $reason);
        $this->attendance->save($mark);
    }
}
