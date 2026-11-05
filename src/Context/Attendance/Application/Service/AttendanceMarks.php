<?php

declare(strict_types=1);

namespace App\Context\Attendance\Application\Service;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Attendance\Domain\Repository\AttendanceRepositoryInterface;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AttendanceMarks
{
    public function __construct(
        private AttendanceRepositoryInterface $attendance,
    ) {
    }

    /**
     * @param list<Uuid> $sessionIds
     *
     * @return ReadableCollection<int, Attendance>
     */
    public function currentOf(array $sessionIds): ReadableCollection
    {
        return $this->attendance->currentMarksOf($sessionIds);
    }
}
