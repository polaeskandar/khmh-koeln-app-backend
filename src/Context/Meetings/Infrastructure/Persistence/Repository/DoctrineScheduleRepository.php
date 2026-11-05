<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineScheduleRepository implements ScheduleRepositoryInterface
{
    private const string LOCK_ROW = 'SELECT id FROM meetings.meeting_schedule WHERE id = ? FOR UPDATE';

    private const string SHARE_ROW = 'SELECT id FROM meetings.meeting_schedule WHERE id = ? FOR SHARE';

    private const string LOCK_OWNED = <<<'SQL'
        SELECT id FROM meetings.meeting_schedule
        WHERE (CAST(:service AS uuid) IS NULL OR service_id = :service)
          AND (CAST(:stage AS uuid) IS NULL OR stage_id = :stage)
        ORDER BY id
        FOR UPDATE
        SQL;

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
    public function active(): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Schedule::class)->findBy(['active' => true]));
    }

    #[Override]
    public function find(Uuid $id): ?Schedule
    {
        return $this->entityManager->find(Schedule::class, $id);
    }

    #[Override]
    public function lockedOf(?Uuid $serviceId, ?Uuid $stageId): ReadableCollection
    {
        $this->entityManager->getConnection()->executeQuery(self::LOCK_OWNED, [
            'service' => $serviceId?->toRfc4122(),
            'stage' => $stageId?->toRfc4122(),
        ]);

        return $this->listed($serviceId, $stageId);
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
        $criteria = new ArrayCollection(['roster.serviceId' => $serviceId, 'roster.stageId' => $stageId])->filter(
            static fn (?Uuid $id): bool => $id !== null,
        );

        return new ArrayCollection($this->entityManager->getRepository(Schedule::class)->findBy($criteria->toArray(), [
            'details.name' => 'ASC',
        ]));
    }
}
