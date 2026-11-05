<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Entity\SessionEvent;
use App\Context\Meetings\Domain\Enum\SessionEventType;
use App\Context\Meetings\Domain\Repository\SessionRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\ListOf;
use App\Library\Types\Model\Type;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineSessionRepository implements SessionRepositoryInterface
{
    private const string LOCK_SCHEDULE = 'SELECT id FROM meetings.meeting_schedule WHERE id = ? FOR UPDATE';

    private const string COUNT_SESSIONS = <<<'SQL'
        SELECT schedule_id, count(*) FROM meetings.meeting_session WHERE schedule_id IN (?) GROUP BY schedule_id
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $doctrine,
    ) {
    }

    #[Override]
    public function find(Uuid $id): ?Session
    {
        return $this->entityManager->find(Session::class, $id);
    }

    #[Override]
    public function withWriteLock(Uuid $id, callable $work): object
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $work): object {
            $session = $this->entityManager->find(Session::class, $id, LockMode::PESSIMISTIC_WRITE);

            return $work($session ?? throw NotFound::of('session'));
        });
    }

    #[Override]
    public function historyOf(Session $session): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(SessionEvent::class)->findBy([
            'session' => $session,
        ], ['occurredAt' => 'ASC', 'id' => 'ASC']));
    }

    /**
     * @param list<LocalDate> $dates
     */
    #[Override]
    public function createMissing(Schedule $schedule, array $dates, Actor $actor, DateTimeImmutable $at): int
    {
        try {
            return $this->entityManager->wrapInTransaction(
                fn (): int => $this->createUnderLock($schedule, $dates, $actor, $at),
            );
        } catch (Throwable $failure) {
            $this->doctrine->resetManager();

            throw $failure;
        }
    }

    #[Override]
    public function ofSchedulesBetween(
        ReadableCollection $schedules,
        LocalDate $from,
        LocalDate $to,
    ): ReadableCollection {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(Session::class, 's')
            ->where('s.schedule IN (:schedules)')
            ->andWhere('s.occurrenceDate BETWEEN :from AND :to')
            ->setParameter('schedules', $schedules->toArray())
            ->setParameter('from', $from, 'local_date')
            ->setParameter('to', $to, 'local_date')
            ->getQuery();

        return new ArrayCollection(ListOf::instances($query->getResult(), Session::class));
    }

    #[Override]
    public function countsBySchedule(ReadableCollection $schedules): ReadableCollection
    {
        $ids = $schedules->map(static fn (Schedule $schedule): string => $schedule->id()->toRfc4122())->getValues();

        $rows = $this->entityManager->getConnection()->fetchAllKeyValue(
            self::COUNT_SESSIONS,
            [$ids],
            [ArrayParameterType::STRING],
        );

        return new ArrayCollection($rows)->map(
            static fn (mixed $count): int => (int) Type::of($count)->toScalarOrNull(),
        );
    }

    /**
     * @param list<LocalDate> $dates
     */
    private function createUnderLock(Schedule $schedule, array $dates, Actor $actor, DateTimeImmutable $at): int
    {
        $this->entityManager->getConnection()->executeQuery(self::LOCK_SCHEDULE, [$schedule->id()->toRfc4122()]);
        $locked = $this->entityManager->find(Schedule::class, $schedule->id());

        if ($locked === null) {
            return 0;
        }

        $missing = new ArrayCollection($dates)->filter(fn (LocalDate $date): bool => !$this->exists($locked, $date));

        foreach ($missing as $date) {
            $session = new Session($locked, $date);
            $this->entityManager->persist($session);

            $this->entityManager->persist(
                new SessionEvent($session, SessionEventType::Created, $at, $actor, note: null),
            );
        }

        return $missing->count();
    }

    private function exists(Schedule $schedule, LocalDate $date): bool
    {
        $found = $this->entityManager
            ->getRepository(Session::class)
            ->findOneBy(['schedule' => $schedule, 'occurrenceDate' => $date]);

        return $found !== null;
    }
}
