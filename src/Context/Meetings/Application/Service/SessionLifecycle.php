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
use App\Platform\Lifecycle\Contract\Transitions;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class SessionLifecycle
{
    public function __construct(
        private SessionRepositoryInterface $sessions,
        private Transitions $transitions,
        private Calendar $calendar,
        #[Target('session_lifecycle')]
        private WorkflowInterface $lifecycle,
    ) {
    }

    /**
     * @param callable(Schedule): void $ensureOpen
     */
    public function open(string $sessionId, string $accountId, callable $ensureOpen): Session
    {
        return $this->locked($sessionId, function (Session $session) use ($accountId, $ensureOpen): Session {
            $ensureOpen($session->schedule());

            return $this->moved($session, 'open', SessionEventType::Opened, $accountId, note: null);
        });
    }

    public function close(string $sessionId, string $accountId): Session
    {
        return $this->locked($sessionId, fn (Session $session): Session => $this->moved(
            $session,
            'close',
            SessionEventType::Closed,
            $accountId,
            note: null,
        ));
    }

    public function reopen(string $sessionId, string $accountId, ?string $reason): Session
    {
        $note = SessionReason::of($reason)->value;

        return $this->locked($sessionId, fn (Session $session): Session => $this->moved(
            $session,
            'reopen',
            SessionEventType::Reopened,
            $accountId,
            $note,
        ));
    }

    public function cancel(string $sessionId, string $accountId, ?string $reason): Session
    {
        $note = SessionReason::of($reason)->value;

        return $this->locked($sessionId, fn (Session $session): Session => $this->moved(
            $session,
            'cancel',
            SessionEventType::Canceled,
            $accountId,
            $note,
        ));
    }

    /**
     * @param callable(Session): Session $work
     */
    private function locked(string $sessionId, callable $work): Session
    {
        $id = Uuid::isValid($sessionId) ? Uuid::fromString($sessionId) : throw NotFound::of('session');

        return $this->sessions->withWriteLock($id, $work);
    }

    private function moved(
        Session $session,
        string $transition,
        SessionEventType $event,
        string $accountId,
        ?string $note,
    ): Session {
        if (!$this->transitions->apply($this->lifecycle, $session, $transition)) {
            return $session;
        }

        $now = $this->calendar->now();
        $by = Uuid::fromString($accountId);
        $session->changeProgress(self::progressAfter($event, $session->progress(), new Stamp($now, $by), $note));
        $this->sessions->append(new SessionEvent($session, $event, $now, Actor::account($by), $note));

        return $session;
    }

    private static function progressAfter(
        SessionEventType $event,
        Progress $progress,
        Stamp $stamp,
        ?string $note,
    ): Progress {
        return match ($event) {
            SessionEventType::Opened => $progress->withOpened($stamp),
            SessionEventType::Closed => $progress->withClosed($stamp),
            SessionEventType::Reopened => $progress->withClosed(Stamp::none()),
            SessionEventType::Canceled => $progress->withCancelReason($note),
            default => $progress,
        };
    }
}
