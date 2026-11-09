<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Repository;

use App\Context\Meetings\Domain\Entity\AttendanceMark;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface AttendanceMarkRepositoryInterface
{
    public function save(AttendanceMark $mark): void;

    public function currentMark(Uuid $sessionId, Uuid $personId): ?AttendanceMark;

    /**
     * @return ReadableCollection<int, AttendanceMark>
     */
    public function marksOf(Uuid $sessionId): ReadableCollection;

    /**
     * @return ReadableCollection<int, AttendanceMark>
     */
    public function removedMarksOf(Uuid $sessionId): ReadableCollection;

    /**
     * @param list<Uuid> $sessionIds
     *
     * @return ReadableCollection<string, list<string>>
     */
    public function presentBySession(array $sessionIds): ReadableCollection;

    /**
     * @return ReadableCollection<int, AttendanceMark>
     */
    public function currentOf(Uuid $sessionId): ReadableCollection;

    /**
     * @return ReadableCollection<int, AttendanceMark>
     */
    public function ofPerson(Uuid $personId): ReadableCollection;
}
