<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Attendance;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\AttendanceRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\RecordedMark;
use App\Context\Meetings\Domain\ValueObject\Removal;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AttendanceMarks
{
    public function __construct(
        private AttendanceRepositoryInterface $attendance,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<Uuid> $sessionIds
     *
     * @return ReadableCollection<string, list<string>>
     */
    public function presentBySession(array $sessionIds): ReadableCollection
    {
        return $this->attendance->presentBySession($sessionIds);
    }

    /**
     * @return ReadableCollection<int, Attendance>
     */
    public function currentMarksOf(Uuid $sessionId): ReadableCollection
    {
        return $this->attendance->currentMarksOf($sessionId);
    }

    /**
     * @return ReadableCollection<int, Attendance>
     */
    public function removedMarksOf(Uuid $sessionId): ReadableCollection
    {
        return $this->attendance->removedMarksOf($sessionId);
    }

    /**
     * @return ReadableCollection<int, Attendance>
     */
    public function ofPerson(Uuid $personId): ReadableCollection
    {
        return $this->attendance->ofPerson($personId);
    }

    public function record(Session $session, Uuid $personId, Uuid $accountId): RecordedMark
    {
        $current = $this->attendance->currentMark($session->id(), $personId);

        if ($current !== null) {
            return new RecordedMark($current, created: false);
        }

        $mark = new Attendance($session, $personId, $this->calendar->now(), $accountId);
        $this->attendance->save($mark);

        return new RecordedMark($mark, created: true);
    }

    public function remove(Uuid $sessionId, Uuid $personId, Uuid $accountId, ?string $reason): void
    {
        $current = $this->attendance->currentMark($sessionId, $personId);

        if ($current === null) {
            return;
        }

        $current->remove(Removal::of($this->calendar->now(), $accountId, $reason));

        $this->attendance->save($current);
    }
}
