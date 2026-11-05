<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\CurrentReport;
use App\Library\Types\Model\ListOf;
use App\Library\Types\Model\Type;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineHomeworkReportRepository implements HomeworkReportRepositoryInterface
{
    private const string REPORT = <<<'SQL'
        INSERT INTO learning.homework_completion (id, assignment_id, person_id, completed_at, completed_by)
        VALUES (:id, :task, :person, :at, :by)
        ON CONFLICT (assignment_id, person_id) WHERE undone_at IS NULL DO NOTHING
        SQL;

    private const string UNDO = <<<'SQL'
        UPDATE learning.homework_completion SET undone_at = :at, undone_by = :by
        WHERE assignment_id = :task AND person_id = :person AND undone_at IS NULL
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
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
     * @param list<Uuid> $personIds
     */
    #[Override]
    public function currentReportsAbout(array $personIds): ReadableCollection
    {
        if ($personIds === []) {
            return new ArrayCollection();
        }

        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('c', 'h', 'l')
            ->from(HomeworkCompletion::class, 'c')
            ->join('c.assignment', 'h')
            ->join('h.lesson', 'l')
            ->where('c.personId IN (:people)')
            ->andWhere('c.undoneAt IS NULL')
            ->setParameter(
                'people',
                new ArrayCollection($personIds)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues(),
                ArrayParameterType::STRING,
            )
            ->getQuery();

        return new ArrayCollection(ListOf::instances($query->getResult(), HomeworkCompletion::class));
    }

    /**
     * @param list<Uuid> $taskIds
     */
    #[Override]
    public function reportedAmong(array $taskIds): ReadableCollection
    {
        $ids = $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select('DISTINCT assignment_id')
            ->from('learning.homework_completion')
            ->where('assignment_id IN (:tasks)')
            ->andWhere('undone_at IS NULL')
            ->setParameter(
                'tasks',
                new ArrayCollection($taskIds)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues(),
                ArrayParameterType::STRING,
            )
            ->fetchFirstColumn();

        return new ArrayCollection($ids)->map(static fn (mixed $id): string => Type::of($id)->toString());
    }

    #[Override]
    public function reportDone(Uuid $taskId, Uuid $personId, DateTimeImmutable $at, ?Uuid $by): bool
    {
        return $this->entityManager->getConnection()->executeStatement(self::REPORT, [
            'id' => Uuid::v7()->toRfc4122(),
            'task' => $taskId->toRfc4122(),
            'person' => $personId->toRfc4122(),
            'at' => $at->format(DateTimeInterface::ATOM),
            'by' => $by?->toRfc4122(),
        ]) === 1;
    }

    #[Override]
    public function currentReport(Uuid $taskId, Uuid $personId): ?CurrentReport
    {
        $row = $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select('completed_at', 'completed_by')
            ->from('learning.homework_completion')
            ->where('assignment_id = :task')
            ->andWhere('person_id = :person')
            ->andWhere('undone_at IS NULL')
            ->setParameter('task', $taskId->toRfc4122())
            ->setParameter('person', $personId->toRfc4122())
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        $by = Type::of($row['completed_by'] ?? null)->toStringOrNull();

        return new CurrentReport(
            new DateTimeImmutable(Type::of($row['completed_at'] ?? null)->toString()),
            $by === null ? null : Uuid::fromString($by),
        );
    }

    #[Override]
    public function undo(Uuid $taskId, Uuid $personId, DateTimeImmutable $at, ?Uuid $by): void
    {
        $this->entityManager->getConnection()->executeStatement(self::UNDO, [
            'task' => $taskId->toRfc4122(),
            'person' => $personId->toRfc4122(),
            'at' => $at->format(DateTimeInterface::ATOM),
            'by' => $by?->toRfc4122(),
        ]);
    }

    #[Override]
    public function allAbout(Uuid $personId): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(HomeworkCompletion::class)->findBy([
            'personId' => $personId,
        ], ['completedAt' => 'ASC', 'id' => 'ASC']));
    }

    #[Override]
    public function allBy(Uuid $accountId): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(HomeworkCompletion::class)->findBy([
            'completedBy' => $accountId,
        ], ['completedAt' => 'ASC', 'id' => 'ASC']));
    }
}
