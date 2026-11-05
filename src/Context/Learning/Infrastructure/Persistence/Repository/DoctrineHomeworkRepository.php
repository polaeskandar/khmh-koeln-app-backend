<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkCounts;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Query\QueryBuilder;
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
    public function find(Uuid $id): ?HomeworkAssignment
    {
        return $this->entityManager->find(HomeworkAssignment::class, $id);
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

    /**
     * @param list<Uuid> $lessonIds
     */
    #[Override]
    public function countsByLesson(array $lessonIds): ReadableCollection
    {
        if ($lessonIds === []) {
            return new ArrayCollection();
        }

        $rows = $this->countQuery($lessonIds)->fetchAllAssociativeIndexed();

        return Collections::rekeyed(
            new ArrayCollection($rows)->map(
                static fn (array $row): HomeworkCounts => new HomeworkCounts(
                    Integers::of($row['published'] ?? 0),
                    Integers::of($row['drafts'] ?? 0),
                ),
            ),
            static fn (int|string $lessonId): string => (string) $lessonId,
        );
    }

    /**
     * @param list<HomeworkAssignment> $tasks
     */
    #[Override]
    public function currentReportsOf(array $tasks): ReadableCollection
    {
        if ($tasks === []) {
            return new ArrayCollection();
        }

        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('c')
            ->from(HomeworkCompletion::class, 'c')
            ->where('c.assignment IN (:tasks)')
            ->andWhere('c.undoneAt IS NULL')
            ->orderBy('c.completedAt')
            ->setParameter('tasks', $tasks)
            ->getQuery();

        return new ArrayCollection(ListOf::instances($query->getResult(), HomeworkCompletion::class));
    }

    /**
     * @param list<Uuid> $lessonIds
     */
    private function countQuery(array $lessonIds): QueryBuilder
    {
        return $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select('lesson_id', "count(*) FILTER (WHERE status = 'published') AS published")
            ->addSelect("count(*) FILTER (WHERE status = 'draft') AS drafts")
            ->from('learning.homework_assignment')
            ->where('lesson_id IN (:lessons)')
            ->andWhere('archived_at IS NULL')
            ->groupBy('lesson_id')
            ->setParameter(
                'lessons',
                new ArrayCollection($lessonIds)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues(),
                ArrayParameterType::STRING,
            );
    }
}
