<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Enum\ServiceFilter;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Infrastructure\Persistence\Repository\Filter\ServiceFilters;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineServiceRepository implements ServiceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Service $service): void
    {
        $this->entityManager->persist($service);
        $this->entityManager->flush();
    }

    #[Override]
    public function tonesInUse(): ReadableCollection
    {
        $stored = new ArrayCollection($this->entityManager->getRepository(Service::class)->findAll())->map(
            self::toneOf(...),
        );

        $pending = Collections::mapPresent(
            new ArrayCollection($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions()),
            self::pendingToneOf(...),
        );

        return new ArrayCollection([...$stored->toArray(), ...$pending->toArray()]);
    }

    #[Override]
    public function find(Uuid $id): ?Service
    {
        return $this->entityManager->find(Service::class, $id);
    }

    #[Override]
    public function findByNameKey(string $key): ?Service
    {
        return $this->entityManager->getRepository(Service::class)->findOneBy(['name.key' => $key]);
    }

    #[Override]
    public function withStructureLock(Uuid $id, callable $work): object
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $work): object {
            $service = $this->entityManager->find(Service::class, $id, LockMode::PESSIMISTIC_WRITE);

            return $work($service ?? throw NotFound::of('service'));
        });
    }

    #[Override]
    public function listed(ServiceFilter $filter): ReadableCollection
    {
        return new ArrayCollection(
            $this->entityManager->getRepository(Service::class)->matching(ServiceFilters::criteria($filter))->toArray(),
        );
    }

    private static function pendingToneOf(object $entity): ?int
    {
        return $entity instanceof Service ? self::toneOf($entity) : null;
    }

    private static function toneOf(Service $service): int
    {
        return $service->tone()->value;
    }
}
