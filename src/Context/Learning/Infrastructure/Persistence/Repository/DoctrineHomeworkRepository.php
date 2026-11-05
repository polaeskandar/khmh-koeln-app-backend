<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkCounts;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use App\Library\Types\Model\Integers;
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
