<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Repository\PersonTraceRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePersonTraceRepository implements PersonTraceRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function forget(Uuid $personId): int
    {
        $connection = $this->entityManager->getConnection();
        $id = $personId->toRfc4122();

        return (int) $connection->delete('learning.homework_completion', ['person_id' => $id]);
    }
}
