<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Enum\SessionEventType;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Context\Meetings\Domain\ValueObject\AttendanceRules;
use App\Context\Meetings\Domain\ValueObject\Stamp;
use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

final readonly class SessionTransitions
{
    public function __construct(
        private SessionRepositoryInterface $sessions,
        private Calendar $calendar,
    ) {
    }

    public function open(string $sessionId, string $accountId): Session
    {
        $today = $this->calendar->today();

        return $this->apply($sessionId, $accountId, SessionEventType::Opened, note: null, change: static fn (
            Session $session,
            Stamp $stamp,
        ): bool => self::opened($session, $today, $stamp));
    }

    public function close(string $sessionId, string $accountId): Session
    {
        return $this->apply($sessionId, $accountId, SessionEventType::Closed, note: null, change: self::closed(...));
    }

    public function reopen(string $sessionId, string $accountId, ?string $reason): Session
    {
        return $this->apply(
            $sessionId,
            $accountId,
            SessionEventType::Reopened,
            note: InputText::of($reason)->trimmed()->toStringOrNull(),
            change: self::reopened(...),
        );
    }

    /**
     * @param callable(Session, Stamp): bool $change
     */
    private function apply(
        string $sessionId,
        string $accountId,
        SessionEventType $type,
        ?string $note,
        callable $change,
    ): Session {
        $by = Uuid::fromString($accountId);
        $id = Uuid::isValid($sessionId) ? Uuid::fromString($sessionId) : throw NotFound::of('session');

        return $this->sessions->withWriteLock($id, function (Session $session) use (
            $by,
            $type,
            $note,
            $change,
        ): Session {
            $now = $this->calendar->now();

            if ($change($session, new Stamp($now, $by))) {
                $this->sessions->append(new SessionEvent($session, $type, $now, Actor::account($by), $note));
            }

            return $session;
        });
    }

    private static function opened(Session $session, LocalDate $today, Stamp $stamp): bool
    {
        $progress = $session->progress();

        if ($progress->status === SessionStatus::Active) {
            return false;
        }

        AttendanceRules::ensureOpenable($progress->status, $session->occurrenceDate(), $today);
        $session->changeProgress($progress->opened($stamp));

        return true;
    }

    private static function closed(Session $session, Stamp $stamp): bool
    {
        $progress = $session->progress();

        if ($progress->status === SessionStatus::Completed) {
            return false;
        }

        AttendanceRules::ensureClosable($progress->status);
        $session->changeProgress($progress->closed($stamp));

        return true;
    }

    private static function reopened(Session $session): bool
    {
        $progress = $session->progress();

        AttendanceRules::ensureReopenable($progress->status);
        $session->changeProgress($progress->reopened());

        return true;
    }
}
