<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Entity\SessionEvent;
use App\Context\Scheduling\Domain\Enum\SessionEventType;
use App\Context\Scheduling\Domain\Repository\SessionRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\Actor;
use App\Context\Scheduling\Domain\ValueObject\SessionPlan;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

final readonly class SessionWrites
{
    public function __construct(
        private SessionRepositoryInterface $sessions,
        private Calendar $calendar,
    ) {
    }

    public function edit(Uuid $sessionId, Uuid $accountId, ?string $title, ?string $location): Session
    {
        $name = InputText::of($title)->collapsed()->toString();
        $place = InputText::of($location)->trimmed()->toStringOrNull();

        return $this->sessions->withWriteLock($sessionId, function (Session $session) use (
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

    private function edited(Session $session, Uuid $accountId): void
    {
        $actor = Actor::account($accountId);

        $this->sessions->append(
            new SessionEvent($session, SessionEventType::Edited, $this->calendar->now(), $actor, null),
        );
    }
}
