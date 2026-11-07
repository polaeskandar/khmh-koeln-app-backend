<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository\Writer;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Enum\SessionEventType;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Context\Meetings\Domain\ValueObject\GenerationResult;
use App\Context\Meetings\Domain\ValueObject\SessionPlan;
use App\Library\Time\Model\LocalDate;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class MissingSessions
{
    private const string LOCK_SCHEDULE = 'SELECT id FROM meetings.meeting_schedule WHERE id = ? FOR UPDATE';

    private const string SESSION_EXISTS = <<<'SQL'
        SELECT 1 FROM meetings.meeting_session WHERE schedule_id = ? AND occurrence_date = ?
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param callable(Schedule): list<LocalDate> $datesOf
     */
    public function create(Uuid $scheduleId, callable $datesOf, Actor $actor, DateTimeImmutable $at): GenerationResult
    {
        return $this->entityManager->wrapInTransaction(
            fn (): GenerationResult => $this->createUnderLock($scheduleId, $datesOf, $actor, $at),
        );
    }

    /**
     * @param callable(Schedule): list<LocalDate> $datesOf
     */
    private function createUnderLock(
        Uuid $scheduleId,
        callable $datesOf,
        Actor $actor,
        DateTimeImmutable $at,
    ): GenerationResult {
        $this->entityManager->getConnection()->executeQuery(self::LOCK_SCHEDULE, [$scheduleId->toRfc4122()]);
        $schedule = $this->entityManager->find(Schedule::class, $scheduleId);

        if ($schedule === null) {
            return new GenerationResult();
        }

        $dates = new ArrayCollection($datesOf($schedule));
        $missing = $dates->filter(fn (LocalDate $date): bool => !$this->exists($scheduleId, $date));

        $events = $missing->map(fn (LocalDate $date): SessionEvent => $this->persisted($schedule, $date, $actor, $at));
        $this->entityManager->flush();

        foreach ($events as $event) {
            $this->entityManager->detach($event);
            $this->entityManager->detach($event->session());
        }

        return new GenerationResult(created: $missing->count(), existing: $dates->count() - $missing->count());
    }

    private function persisted(Schedule $schedule, LocalDate $date, Actor $actor, DateTimeImmutable $at): SessionEvent
    {
        $session = new Session($schedule, $date, SessionPlan::of($schedule));
        $event = new SessionEvent($session, SessionEventType::Created, $at, $actor, note: null);
        $this->entityManager->persist($session);
        $this->entityManager->persist($event);

        return $event;
    }

    private function exists(Uuid $scheduleId, LocalDate $date): bool
    {
        return $this->entityManager->getConnection()->fetchOne(self::SESSION_EXISTS, [
            $scheduleId->toRfc4122(),
            $date->toString(),
        ]) !== false;
    }
}
