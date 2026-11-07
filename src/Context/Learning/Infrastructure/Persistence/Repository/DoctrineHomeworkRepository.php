<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineHomeworkRepository implements HomeworkRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(HomeworkAssignment $assignment): void
    {
        $this->entityManager->persist($assignment);
        $this->entityManager->flush();
    }

    #[Override]
    public function withLock(Uuid $id, callable $work): object
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $work): object {
            $loaded = $this->entityManager->getUnitOfWork()->tryGetById(['id' => $id], HomeworkAssignment::class);

            if ($loaded !== false) {
                $this->entityManager->detach($loaded);
            }

            $assignment = $this->entityManager->find(HomeworkAssignment::class, $id, LockMode::PESSIMISTIC_WRITE);

            return $work($assignment ?? throw NotFound::of('homework'));
        });
    }
}
