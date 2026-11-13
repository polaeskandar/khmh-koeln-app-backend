<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Query;

use App\Context\Scheduling\Domain\Entity\Attendance;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface AttendanceQueryInterface
{
    /**
     * @param list<Uuid> $sessionIds
     *
     * @return ReadableCollection<string, list<string>>
     */
    public function listPresentBySession(array $sessionIds): ReadableCollection;

    /**
     * @return ReadableCollection<int, Attendance>
     */
    public function listCurrentMarksOf(Uuid $sessionId): ReadableCollection;

    /**
     * @return ReadableCollection<int, Attendance>
     */
    public function listRemovedMarksOf(Uuid $sessionId): ReadableCollection;

    /**
     * @return ReadableCollection<int, Attendance>
     */
    public function listMarksOfPerson(Uuid $personId): ReadableCollection;
}
