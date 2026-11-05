<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStageRepository implements StageRepositoryInterface
{
    private const string SERVICE_OF_STAGE = 'SELECT service_id FROM organization.stage WHERE id = ?';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Stage $stage): void
    {
        $this->entityManager->persist($stage);
        $this->entityManager->flush();
    }

    #[Override]
    public function belongsTo(Uuid $stageId, Uuid $serviceId): bool
    {
        return $this->entityManager
            ->getRepository(Stage::class)
            ->count([
                'id' => $stageId,
                'service' => $serviceId,
            ]) > 0;
    }

    /**
     * @param list<Uuid> $ids
     */
    #[Override]
    public function findByIds(array $ids): ReadableCollection
    {
        return new ArrayCollection(
            $ids === [] ? [] : $this->entityManager->getRepository(Stage::class)->findBy(['id' => $ids]),
        );
    }

    #[Override]
    public function find(Uuid $id): ?Stage
    {
        return $this->entityManager->find(Stage::class, $id);
    }

    #[Override]
    public function serviceIdOf(Uuid $stageId): ?Uuid
    {
        $connection = $this->entityManager->getConnection();
        $serviceId = Type::of($connection->fetchOne(self::SERVICE_OF_STAGE, [$stageId->toRfc4122()]))->toStringOrNull();

        return $serviceId === null ? null : Uuid::fromString($serviceId);
    }

    #[Override]
    public function ofService(Service $service): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Stage::class)->findBy(['service' => $service], [
            'position' => 'ASC',
            'name.key' => 'ASC',
        ]));
    }

    #[Override]
    public function findInService(Service $service, string $nameKey): ?Stage
    {
        return $this->entityManager
            ->getRepository(Stage::class)
            ->findOneBy(['service' => $service, 'name.key' => $nameKey]);
    }

    #[Override]
    public function activeIn(ReadableCollection $services): ReadableCollection
    {
        if ($services->isEmpty()) {
            return new ArrayCollection();
        }

        return new ArrayCollection($this->entityManager->getRepository(Stage::class)->findBy([
            'service' => $services->toArray(),
            'archivedAt' => null,
        ], ['position' => 'ASC', 'name.key' => 'ASC']));
    }

    #[Override]
    public function archived(): ReadableCollection
    {
        return $this->entityManager
            ->getRepository(Stage::class)
            ->matching(Criteria::create()->where(Criteria::expr()->neq('archivedAt', null)));
    }
}
