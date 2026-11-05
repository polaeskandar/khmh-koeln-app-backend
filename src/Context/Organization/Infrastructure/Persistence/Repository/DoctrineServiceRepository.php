<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Enum\ServiceFilter;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineServiceRepository implements ServiceRepositoryInterface
{
    private const string LOCK_ROW = 'SELECT id FROM organization.service WHERE id = ? FOR UPDATE';

    private const string SHARE_ROWS = 'SELECT id FROM organization.service WHERE id IN (:ids) ORDER BY id FOR SHARE';

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
    public function remove(Service $service): void
    {
        $this->entityManager->remove($service);
        $this->entityManager->flush();
    }

    #[Override]
    public function tonesInUse(): ReadableCollection
    {
        $stored = new ArrayCollection($this->entityManager->getRepository(Service::class)->findAll())->map(
            ServiceTones::of(...),
        );

        $pending = Collections::mapPresent(
            new ArrayCollection($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions()),
            ServiceTones::pendingOf(...),
        );

        return new ArrayCollection([...$stored->toArray(), ...$pending->toArray()]);
    }

    /**
     * @param list<Uuid> $ids
     */
    #[Override]
    public function findByIds(array $ids): ReadableCollection
    {
        return new ArrayCollection(
            $ids === [] ? [] : $this->entityManager->getRepository(Service::class)->findBy(['id' => $ids]),
        );
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
            $this->entityManager->getConnection()->executeQuery(self::LOCK_ROW, [$id->toRfc4122()]);
            $loaded = $this->entityManager->getUnitOfWork()->getIdentityMap();

            foreach ([...($loaded[Service::class] ?? []), ...($loaded[Stage::class] ?? [])] as $entity) {
                $this->entityManager->detach($entity);
            }

            return $work($this->entityManager->find(Service::class, $id) ?? throw NotFound::of('service'));
        });
    }

    /**
     * @param list<Uuid> $ids
     */
    #[Override]
    public function withShareLock(array $ids, callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(function () use ($ids, $work): mixed {
            $this->entityManager->getConnection()->executeQuery(
                self::SHARE_ROWS,
                [
                    'ids' => new ArrayCollection($ids)->map(
                        static fn (Uuid $id): string => $id->toRfc4122(),
                    )->getValues(),
                ],
                ['ids' => ArrayParameterType::STRING],
            );

            return $work();
        });
    }

    #[Override]
    public function listed(ServiceFilter $filter): ReadableCollection
    {
        return $this->entityManager->getRepository(Service::class)->matching(ServiceFilters::criteria($filter));
    }
}
