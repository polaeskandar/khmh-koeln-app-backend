<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
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
    public function remove(HomeworkAssignment $assignment): void
    {
        $this->entityManager->remove($assignment);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(Uuid $id): ?HomeworkAssignment
    {
        return $this->entityManager->find(HomeworkAssignment::class, $id);
    }

    /**
     * @return list<HomeworkAssignment>
     */
    #[Override]
    public function ofStage(Uuid $stageId): array
    {
        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('h', 'l')
            ->from(HomeworkAssignment::class, 'h')
            ->join('h.lesson', 'l')
            ->where('h.stageId = :stage')
            ->setParameter('stage', $stageId, 'uuid')
            ->getQuery();

        return ListOf::instances($query->getResult(), HomeworkAssignment::class);
    }

    #[Override]
    public function withLock(Uuid $id, callable $work): object
    {
        return $this->entityManager->wrapInTransaction(function () use ($id, $work): object {
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
     * @param list<Uuid> $stageIds
     *
     * @return list<HomeworkAssignment>
     */
    #[Override]
    public function publishedInStages(array $stageIds): array
    {
        if ($stageIds === []) {
            return [];
        }

        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('h', 'l')
            ->from(HomeworkAssignment::class, 'h')
            ->join('h.lesson', 'l')
            ->where('h.stageId IN (:stages)')
            ->andWhere('h.state.status = :published')
            ->setParameter('stages', self::ids($stageIds), ArrayParameterType::STRING)
            ->setParameter('published', HomeworkStatus::Published)
            ->getQuery();

        return ListOf::instances($query->getResult(), HomeworkAssignment::class);
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<string>
     */
    private static function ids(array $ids): array
    {
        return new ArrayCollection($ids)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues();
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
            ->setParameter('lessons', self::ids($lessonIds), ArrayParameterType::STRING);
    }
}
