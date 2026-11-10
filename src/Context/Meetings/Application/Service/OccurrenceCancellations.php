<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Context\Meetings\Domain\ValueObject\ScheduleDate;
use App\Context\Meetings\Domain\ValueObject\SessionReason;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use App\Platform\Doctrine\Contract\Transactions;
use Symfony\Component\Uid\Uuid;

final readonly class OccurrenceCancellations
{
    public function __construct(
        private MeetingSchedules $schedules,
        private SessionRepositoryInterface $sessions,
        private SessionLifecycle $lifecycle,
        private Transactions $transactions,
        private Calendar $calendar,
    ) {
    }

    public function cancelable(Uuid $scheduleId, LocalDate $date): ScheduleDate
    {
        return ScheduleDate::cancelable($this->schedules->get($scheduleId), $date, $this->calendar->today());
    }

    public function cancel(ScheduleDate $planned, Uuid $accountId, ?string $reason): Session
    {
        $canceled = null;
        $note = SessionReason::of($reason)->value;

        $this->transactions->run(function () use ($planned, $accountId, $note, &$canceled): void {
            $session = $this->materialized($planned, $accountId);
            $canceled = $this->lifecycle->cancel($session->id(), $accountId, $note);
        });

        return $canceled ?? throw NotFound::of('session');
    }

    private function materialized(ScheduleDate $planned, Uuid $accountId): Session
    {
        $actor = Actor::account($accountId);
        $date = $planned->date;

        $this->sessions->createMissing(
            $planned->schedule->id(),
            static fn (): array => [$date],
            $actor,
            $this->calendar->now(),
        );

        return $this->sessions->findOn($planned->schedule, $planned->date) ?? throw NotFound::of('session');
    }
}
