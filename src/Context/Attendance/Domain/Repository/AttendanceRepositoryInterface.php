<?php

declare(strict_types=1);

namespace App\Context\Attendance\Domain\Repository;

use App\Context\Attendance\Domain\Entity\Attendance;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface AttendanceRepositoryInterface
{
    public function save(Attendance $attendance): void;

    public function currentMark(Uuid $sessionId, Uuid $personId): ?Attendance;

    /**
     * @return ReadableCollection<int, Attendance>
     */
    public function marksOf(Uuid $sessionId): ReadableCollection;

    /**
     * @param list<Uuid> $sessionIds
     *
     * @return ReadableCollection<string, list<string>>
     */
    public function presentBySession(array $sessionIds): ReadableCollection;
}
