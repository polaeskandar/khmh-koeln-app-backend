<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Enum\SessionEventType;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Context\Meetings\Domain\ValueObject\SessionPlan;
use App\Library\Problem\Exception\NotFound;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

final readonly class SessionDetailChanges
{
    public function __construct(
        private SessionRepositoryInterface $sessions,
        private Calendar $calendar,
    ) {
    }

    public function change(string $sessionId, string $accountId, ?string $title, ?string $location): Session
    {
        $id = Uuid::isValid($sessionId) ? Uuid::fromString($sessionId) : throw NotFound::of('session');
        $name = InputText::of($title)->collapsed()->toString();
        $place = InputText::of($location)->trimmed()->toStringOrNull();

        return $this->sessions->withWriteLock($id, function (Session $session) use (
            $accountId,
            $name,
            $place,
        ): Session {
            $session->progress()->ensureNotCanceled();
            $plan = $session->plan();

            if ($plan->title !== $name || $plan->location !== $place) {
                $session->changePlan(new SessionPlan($name, $place, $plan->timeSlot, $plan->rosterMode));
                $this->edited($session, $accountId);
            }

            return $session;
        });
    }

    private function edited(Session $session, string $accountId): void
    {
        $actor = Actor::account(Uuid::fromString($accountId));

        $this->sessions->append(
            new SessionEvent($session, SessionEventType::Edited, $this->calendar->now(), $actor, null),
        );
    }
}
