<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Enum\SessionEventType;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Context\Meetings\Domain\ValueObject\Stamp;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Client\Calendar;
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
        $today = $this->calendar->today();

        return $this->apply($sessionId, $accountId, SessionEventType::Opened, note: null, change: static function (
            Session $session,
            Stamp $stamp,
        ) use ($today, $ensureOpen): bool {
            $ensureOpen($session->schedule());

            return $session->open($today, $stamp);
        });
    }

    public function close(string $sessionId, string $accountId): Session
    {
        return $this->apply($sessionId, $accountId, SessionEventType::Closed, note: null, change: static fn (
            Session $session,
            Stamp $stamp,
        ): bool => $session->close($stamp));
    }

    public function reopen(string $sessionId, string $accountId, ?string $reason): Session
    {
        return $this->apply(
            $sessionId,
            $accountId,
            SessionEventType::Reopened,
            note: InputText::of($reason)->trimmed()->toStringOrNull(),
            change: static fn (Session $session): bool => $session->reopen(),
        );
    }

    public function cancel(string $sessionId, string $accountId, ?string $reason): Session
    {
        $note = InputText::of($reason)->trimmed()->toStringOrNull();

        return $this->apply(
            $sessionId,
            $accountId,
            SessionEventType::Canceled,
            note: $note,
            change: static fn (Session $session): bool => $session->cancel($note),
        );
    }

    public function rename(string $sessionId, string $accountId, ?string $title, ?string $location): Session
    {
        $name = InputText::of($title)->collapsed()->toString();
        $place = InputText::of($location)->trimmed()->toStringOrNull();

        return $this->apply(
            $sessionId,
            $accountId,
            SessionEventType::Edited,
            note: null,
            change: static fn (Session $session): bool => $session->rename($name, $place),
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

        return $this->sessions->withWriteLock(Uuid::fromString($sessionId), function (Session $session) use (
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
}
