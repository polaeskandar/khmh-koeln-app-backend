<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Entity\SessionEvent;
use App\Context\Scheduling\Domain\Enum\SessionEventType;
use App\Context\Scheduling\Domain\Repository\SessionRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\Actor;
use App\Context\Scheduling\Domain\ValueObject\Progress;
use App\Context\Scheduling\Domain\ValueObject\SessionReason;
use App\Context\Scheduling\Domain\ValueObject\Stamp;
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
    public function open(Uuid $sessionId, Uuid $accountId, callable $ensureOpen): Session
    {
        return $this->sessions->withWriteLock($sessionId, function (Session $session) use (
            $accountId,
            $ensureOpen,
        ): Session {
            $ensureOpen($session->schedule());

            return $this->moved($session, 'open', SessionEventType::Opened, $accountId, note: null);
        });
    }

    public function close(Uuid $sessionId, Uuid $accountId): Session
    {
        return $this->sessions->withWriteLock($sessionId, fn (Session $session): Session => $this->moved(
            $session,
            'close',
            SessionEventType::Closed,
            $accountId,
            note: null,
        ));
    }

    public function reopen(Uuid $sessionId, Uuid $accountId, ?string $reason): Session
    {
        $note = SessionReason::of($reason)->value;

        return $this->sessions->withWriteLock($sessionId, fn (Session $session): Session => $this->moved(
            $session,
            'reopen',
            SessionEventType::Reopened,
            $accountId,
            $note,
        ));
    }

    public function cancel(Uuid $sessionId, Uuid $accountId, ?string $reason): Session
    {
        $note = SessionReason::of($reason)->value;

        return $this->sessions->withWriteLock($sessionId, fn (Session $session): Session => $this->moved(
            $session,
            'cancel',
            SessionEventType::Canceled,
            $accountId,
            $note,
        ));
    }

    private function moved(
        Session $session,
        string $transition,
        SessionEventType $event,
        Uuid $accountId,
        ?string $note,
    ): Session {
        if (!$this->transitions->apply($this->lifecycle, $session, $transition)) {
            return $session;
        }

        $now = $this->calendar->now();
        $stamp = new Stamp($now, $accountId);
        $session->changeProgress(self::progressAfter($event, $session->progress(), $stamp, $note));
        $this->sessions->append(new SessionEvent($session, $event, $now, Actor::account($accountId), $note));

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
