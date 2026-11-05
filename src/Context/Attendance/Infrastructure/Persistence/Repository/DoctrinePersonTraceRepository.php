<?php

declare(strict_types=1);

namespace App\Context\Attendance\Infrastructure\Persistence\Repository;

use App\Context\Attendance\Domain\Repository\PersonTraceRepositoryInterface;
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

        return (int) $connection->delete('attendance.attendance', ['person_id' => $id]);
    }
}
