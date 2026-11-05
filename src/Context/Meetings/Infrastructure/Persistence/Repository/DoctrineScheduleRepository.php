<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Repository\ScheduleRepositoryInterface;
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
