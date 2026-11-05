<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineScheduleRepository implements ScheduleRepositoryInterface
{
    private const string ACTIVE_IDS = 'SELECT id FROM meetings.meeting_schedule WHERE active ORDER BY id';

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
