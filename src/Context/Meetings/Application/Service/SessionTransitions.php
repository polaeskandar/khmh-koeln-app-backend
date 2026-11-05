<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Enum\SessionEventType;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Context\Meetings\Domain\ValueObject\Progress;
use App\Context\Meetings\Domain\ValueObject\SessionReason;
use App\Context\Meetings\Domain\ValueObject\Stamp;
use App\Library\Problem\Exception\NotFound;
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

    /**
     * @param callable(Schedule): void $ensureOpen
     */
    public function open(string $sessionId, string $accountId, callable $ensureOpen): Session
    {
        return $this->apply($sessionId, $accountId, SessionEventType::Opened, note: null, change: static function (
            Session $session,
            Stamp $stamp,
            LocalDate $today,
        ) use ($ensureOpen): Progress {
            $ensureOpen($session->schedule());

            return $session->progress()->opened($stamp, $session->occurrenceDate(), $today);
        });
    }

    public function close(string $sessionId, string $accountId): Session
    {
        return $this->apply($sessionId, $accountId, SessionEventType::Closed, note: null, change: static fn (
            Session $session,
            Stamp $stamp,
        ): Progress => $session->progress()->closed($stamp));
    }

    public function reopen(string $sessionId, string $accountId, ?string $reason): Session
    {
        return $this->apply(
            $sessionId,
            $accountId,
            SessionEventType::Reopened,
            note: SessionReason::of($reason)->value,
            change: static fn (Session $session): Progress => $session->progress()->reopened(),
        );
    }

    public function cancel(string $sessionId, string $accountId, ?string $reason): Session
    {
        $note = SessionReason::of($reason)->value;

        return $this->apply(
            $sessionId,
            $accountId,
            SessionEventType::Canceled,
            note: $note,
            change: static fn (Session $session): Progress => $session->progress()->canceled($note),
        );
    }

    /**
     * @param callable(Session, Stamp, LocalDate): Progress $change
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
            $progress = $session->progress();
            $next = $change($session, new Stamp($now, $by), $this->calendar->localDateOf($now));

            if ($next !== $progress) {
                $session->changeProgress($next);
                $this->sessions->append(new SessionEvent($session, $type, $now, Actor::account($by), $note));
            }

            return $session;
        });
    }
}
