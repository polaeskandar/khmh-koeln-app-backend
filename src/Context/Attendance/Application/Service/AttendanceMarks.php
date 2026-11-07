<?php

declare(strict_types=1);

namespace App\Context\Attendance\Application\Service;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Attendance\Domain\Repository\AttendanceRepositoryInterface;
use App\Context\Attendance\Domain\ValueObject\RecordedMark;
use App\Context\Attendance\Domain\ValueObject\Removal;
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
    public function currentOf(Uuid $sessionId): ReadableCollection
    {
        return $this->attendance->marksOf($sessionId)->filter(static fn (Attendance $mark): bool => $mark->isCurrent());
    }

    /**
     * @return ReadableCollection<int, Attendance>
     */
    public function removedOf(Uuid $sessionId): ReadableCollection
    {
        return $this->attendance->removedMarksOf($sessionId);
    }

    public function record(Uuid $sessionId, Uuid $personId, string $accountId): RecordedMark
    {
        $current = $this->attendance->currentMark($sessionId, $personId);

        if ($current !== null) {
            return new RecordedMark($current, created: false);
        }

        $mark = new Attendance($sessionId, $personId, $this->calendar->now(), Uuid::fromString($accountId));
        $this->attendance->save($mark);

        return new RecordedMark($mark, created: true);
    }

    public function remove(Uuid $sessionId, Uuid $personId, string $accountId, ?string $reason): void
    {
        $current = $this->attendance->currentMark($sessionId, $personId);

        if ($current === null) {
            return;
        }

        $current->remove(Removal::of($this->calendar->now(), Uuid::fromString($accountId), $reason));

        $this->attendance->save($current);
    }
}
