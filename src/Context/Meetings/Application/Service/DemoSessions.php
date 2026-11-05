<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Enum\SessionEventType;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Context\Meetings\Domain\ValueObject\HeldSessions;
use App\Context\Meetings\Domain\ValueObject\Stamp;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalTime;
use App\Platform\Doctrine\Contract\Transactions;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class DemoSessions
{
    public function __construct(
        private SessionRepositoryInterface $sessions,
        private Transactions $transactions,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<Schedule> $schedules the class's schedules, its Sunday class first
     */
    public function hold(array $schedules, Uuid $servantId): HeldSessions
    {
        $today = $this->calendar->today();
        $sunday = $today->addDays(-$today->isoWeekday());
        $sundayClass = $schedules[0] ?? throw new LogicException('The class has no Sunday class.');
        $opened = $this->calendar->instantAt($sunday, LocalTime::of('12:00'));
        $this->sessions->createMissing($sundayClass, [$sunday], Actor::account($servantId), $opened);
        $closed = $this->sessions->findOn($sundayClass, $sunday) ?? throw new LogicException('No Sunday session.');
        $this->opened($closed, $opened, $servantId);
        $this->closed($closed, $this->calendar->instantAt($sunday, LocalTime::of('13:15')), $servantId);
        $open = $this->sessions->ofSchedulesOn(new ArrayCollection($schedules), [$today])->first();

        if (!$open instanceof Session) {
            throw new LogicException('The class meets every day.');
        }

        $now = $this->calendar->now()->toDateTimeImmutable();
        $this->opened($open, $now, $servantId);

        return new HeldSessions($closed->id(), $opened, $open->id(), $now);
    }

    private function opened(Session $session, DateTimeImmutable $at, Uuid $servantId): void
    {
        $today = $this->calendar->today();

        $this->transactions->run(function () use ($session, $at, $servantId, $today): void {
            $session->open($today, new Stamp($at, $servantId));
            $this->sessions->append(
                new SessionEvent($session, SessionEventType::Opened, $at, Actor::account($servantId), note: null),
            );
        });
    }

    private function closed(Session $session, DateTimeImmutable $at, Uuid $servantId): void
    {
        $this->transactions->run(function () use ($session, $at, $servantId): void {
            $session->close(new Stamp($at, $servantId));
            $this->sessions->append(
                new SessionEvent($session, SessionEventType::Closed, $at, Actor::account($servantId), note: null),
            );
        });
    }
}
