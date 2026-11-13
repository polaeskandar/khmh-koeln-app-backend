<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Repository;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\Entity\SessionEvent;
use App\Context\Scheduling\Domain\Repository\SessionRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\Actor;
use App\Context\Scheduling\Domain\ValueObject\GenerationResult;
use App\Context\Scheduling\Infrastructure\Persistence\Repository\Writer\MissingSessions;
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
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSessionRepository implements SessionRepositoryInterface
{
    private const string COUNT_SESSIONS = <<<'SQL'
        SELECT schedule_id, count(*) FROM scheduling.session WHERE schedule_id IN (?) GROUP BY schedule_id
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MissingSessions $missing,
    ) {
    }

    #[Override]
    public function find(Uuid $id): ?Session
    {
        return $this->entityManager->find(Session::class, $id);
    }

    #[Override]
    public function append(SessionEvent $event): void
    {
        $this->entityManager->persist($event);
    }

    #[Override]
    public function findOn(Schedule $schedule, LocalDate $date): ?Session
    {
        return $this->entityManager
            ->getRepository(Session::class)
            ->findOneBy(['schedule' => $schedule, 'occurrenceDate' => $date]);
    }

    #[Override]
    public function withWriteLock(Uuid $id, callable $work): object
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $work): object {
            $loaded = $this->entityManager->getUnitOfWork()->tryGetById(['id' => $id], Session::class);

            if ($loaded !== false) {
                $this->entityManager->detach($loaded);
            }

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
     * @param callable(Schedule): list<LocalDate> $datesOf
     */
    #[Override]
    public function createMissing(
        Uuid $scheduleId,
        callable $datesOf,
        Actor $actor,
        DateTimeImmutable $at,
    ): GenerationResult {
        return $this->missing->create($scheduleId, $datesOf, $actor, $at);
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

    /**
     * @param list<LocalDate> $dates
     */
    #[Override]
    public function ofSchedulesOn(ReadableCollection $schedules, array $dates): ReadableCollection
    {
        return new ArrayCollection($this->entityManager
            ->getRepository(Session::class)
            ->findBy([
                'schedule' => $schedules->toArray(),
                'occurrenceDate' => $dates,
            ]));
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
}
