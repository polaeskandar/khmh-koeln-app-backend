<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineScheduleRepository implements ScheduleRepositoryInterface
{
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
