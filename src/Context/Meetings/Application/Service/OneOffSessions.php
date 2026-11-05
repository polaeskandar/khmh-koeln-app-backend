<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Context\Meetings\Domain\ValueObject\MeetingMoment;
use App\Context\Meetings\Domain\ValueObject\OneOffDraft;
use App\Context\Meetings\Domain\ValueObject\OneOffSlot;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\Instant;
use App\Platform\Doctrine\Contract\Transactions;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class OneOffSessions
{
    public function __construct(
        private ScheduleRepositoryInterface $schedules,
        private SessionRepositoryInterface $sessions,
        private Transactions $transactions,
        private Calendar $calendar,
    ) {
    }

    public function create(OneOffDraft $draft, string $accountId): Session
    {
        $start = $this->momentOf($draft->startsAt, field: 'startsAt');
        $end = $draft->endsAt === null ? null : $this->momentOf($draft->endsAt, field: 'endsAt');
        $slot = OneOffSlot::between($start, $end, $this->calendar->today());
        $schedule = Schedule::oneOff($draft->roster, $draft->details(), $slot->date, $slot->times);
        $actor = Actor::account(Uuid::fromString($accountId));

        return $this->transactions->run(function () use ($schedule, $slot, $actor): Session {
            $this->schedules->save($schedule);
            $this->sessions->createMissing($schedule, [$slot->date], $actor, $this->calendar->now());

            return $this->sessions->findOn($schedule, $slot->date) ?? throw new LogicException('No one-off session.');
        });
    }

    private function momentOf(string $value, string $field): MeetingMoment
    {
        $instant = Instant::parse($value) ?? throw InvalidInput::field($field, 'validation.invalidDate');

        return new MeetingMoment(
            $instant,
            $this->calendar->localDateOf($instant),
            $this->calendar->localTimeOf($instant),
        );
    }
}
