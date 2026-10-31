<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStageRepository implements StageRepositoryInterface
{
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
}
