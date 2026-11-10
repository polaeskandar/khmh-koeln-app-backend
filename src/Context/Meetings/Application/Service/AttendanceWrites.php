<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Attendance;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\AttendanceRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\Validation\AttendanceRule;
use App\Context\Meetings\Domain\ValueObject\RecordedMark;
use App\Context\Meetings\Domain\ValueObject\Removal;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

final readonly class AttendanceWrites
{
    public function __construct(
        private AttendanceRepositoryInterface $attendance,
        private SessionRepositoryInterface $sessions,
        private Calendar $calendar,
    ) {
    }

    /**
     * @template T of object
     *
     * @param callable(Session): T $work
     *
     * @return T
     */
    public function whileMarkable(Uuid $sessionId, callable $work): object
    {
        return $this->sessions->withWriteLock($sessionId, static function (Session $session) use ($work): object {
            AttendanceRule::ensureMarkable($session->progress()->status);

            return $work($session);
        });
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
