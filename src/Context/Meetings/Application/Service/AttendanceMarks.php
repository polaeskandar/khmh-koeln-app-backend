<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\AttendanceMark;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\AttendanceMarkRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\RecordedMark;
use App\Context\Meetings\Domain\ValueObject\Removal;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AttendanceMarks
{
    public function __construct(
        private AttendanceMarkRepositoryInterface $marks,
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
        return $this->marks->presentBySession($sessionIds);
    }

    /**
     * @return ReadableCollection<int, AttendanceMark>
     */
    public function currentOf(Uuid $sessionId): ReadableCollection
    {
        return $this->marks->currentOf($sessionId);
    }

    /**
     * @return ReadableCollection<int, AttendanceMark>
     */
    public function removedOf(Uuid $sessionId): ReadableCollection
    {
        return $this->marks->removedMarksOf($sessionId);
    }

    /**
     * @return ReadableCollection<int, AttendanceMark>
     */
    public function ofPerson(Uuid $personId): ReadableCollection
    {
        return $this->marks->ofPerson($personId);
    }

    public function record(Session $session, Uuid $personId, string $accountId): RecordedMark
    {
        $current = $this->marks->currentMark($session->id(), $personId);

        if ($current !== null) {
            return new RecordedMark($current, created: false);
        }

        $mark = new AttendanceMark($session, $personId, $this->calendar->now(), Uuid::fromString($accountId));
        $this->marks->save($mark);

        return new RecordedMark($mark, created: true);
    }

    public function remove(Uuid $sessionId, Uuid $personId, string $accountId, ?string $reason): void
    {
        $current = $this->marks->currentMark($sessionId, $personId);

        if ($current === null) {
            return;
        }

        $current->remove(Removal::of($this->calendar->now(), Uuid::fromString($accountId), $reason));

        $this->marks->save($current);
    }
}
