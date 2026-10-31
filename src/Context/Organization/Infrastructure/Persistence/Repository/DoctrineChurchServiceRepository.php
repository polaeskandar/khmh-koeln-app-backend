<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Entity\ChurchService;
use App\Context\Organization\Domain\Repository\ChurchServiceRepositoryInterface;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineChurchServiceRepository implements ChurchServiceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(ChurchService $service): void
    {
        $this->entityManager->persist($service);
        $this->entityManager->flush();
    }

    #[Override]
    public function tonesInUse(): ReadableCollection
    {
        $stored = new ArrayCollection($this->entityManager->getRepository(ChurchService::class)->findAll())->map(
            self::toneOf(...),
        );

        $pending = Collections::mapPresent(
            new ArrayCollection($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions()),
            self::pendingToneOf(...),
        );

        return new ArrayCollection([...$stored->toArray(), ...$pending->toArray()]);
    }

    private static function pendingToneOf(object $entity): ?int
    {
        return $entity instanceof ChurchService ? self::toneOf($entity) : null;
    }

    private static function toneOf(ChurchService $service): int
    {
        return $service->tone()->value;
    }
}
