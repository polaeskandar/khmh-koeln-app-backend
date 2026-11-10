<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\SessionPlan;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\ListOf;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineScheduleRepository implements ScheduleRepositoryInterface
{
    private const string ACTIVE_IDS = 'SELECT id FROM meetings.meeting_schedule WHERE active ORDER BY id';

    private const string REPLAN_UPCOMING = <<<'SQL'
        UPDATE meetings.meeting_session
        SET title = ?, location = ?, start_time = ?, end_time = ?, roster_mode = ?
        WHERE schedule_id = ? AND status = ? AND occurrence_date >= ?
        SQL;

    private const string LOCK_ROW = 'SELECT id FROM meetings.meeting_schedule WHERE id = ? FOR UPDATE';

    private const string SHARE_ROW = 'SELECT id FROM meetings.meeting_schedule WHERE id = ? FOR SHARE';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Schedule $schedule): void
    {
        $this->entityManager->persist($schedule);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(Schedule $schedule): void
    {
        $this->entityManager->remove($schedule);
        $this->entityManager->flush();
    }

    #[Override]
    public function withWriteLock(Uuid $id, callable $work): void
    {
        $this->entityManager->wrapInTransaction(function () use ($id, $work): void {
            $this->entityManager->getConnection()->executeQuery(self::LOCK_ROW, [$id->toRfc4122()]);

            $work($this->entityManager->find(Schedule::class, $id) ?? throw NotFound::of('schedule'));
        });
    }

    #[Override]
    public function withShareLock(Uuid $id, callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $work): mixed {
            $this->entityManager->getConnection()->executeQuery(self::SHARE_ROW, [$id->toRfc4122()]);

            return $work();
        });
    }

    #[Override]
    public function replanUpcoming(Schedule $schedule, LocalDate $from): void
    {
        $plan = SessionPlan::of($schedule);

        $this->entityManager->getConnection()->executeStatement(self::REPLAN_UPCOMING, [
            $plan->title,
            $plan->location,
            $plan->timeSlot->startTime->toString(),
            $plan->timeSlot->endTime?->toString(),
            $plan->rosterMode->value,
            $schedule->id()->toRfc4122(),
            SessionStatus::Scheduled->value,
            $from->toString(),
        ]);
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function activeIds(): array
    {
        return new ArrayCollection($this->entityManager->getConnection()->fetchFirstColumn(self::ACTIVE_IDS))->map(
            static fn (mixed $id): Uuid => Uuid::fromString(Type::of($id)->toString()),
        )->getValues();
    }

    #[Override]
    public function find(Uuid $id): ?Schedule
    {
        return $this->entityManager->find(Schedule::class, $id);
    }

    /**
     * @param list<Uuid> $ids
     */
    #[Override]
    public function findByIds(array $ids): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Schedule::class)->findBy(['id' => $ids]));
    }

    #[Override]
    public function listed(?Uuid $serviceId, ?Uuid $stageId): ReadableCollection
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->addSelect('LOWER(s.details.name) AS HIDDEN nameKey')
            ->from(Schedule::class, 's')
            ->orderBy('nameKey')
            ->addOrderBy('s.id');

        if ($serviceId !== null) {
            $query->andWhere('s.roster.serviceId = :service')->setParameter('service', $serviceId, UuidType::NAME);
        }

        if ($stageId !== null) {
            $query->andWhere('s.roster.stageId = :stage')->setParameter('stage', $stageId, UuidType::NAME);
        }

        return new ArrayCollection(ListOf::instances($query->getQuery()->getResult(), Schedule::class));
    }
}
