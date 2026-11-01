<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;

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
    public function active(): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Service::class)->findBy(['archivedAt' => null], [
            'name.value' => 'ASC',
        ]));
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
