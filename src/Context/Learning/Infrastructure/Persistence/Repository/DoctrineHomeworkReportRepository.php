<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineHomeworkReportRepository implements HomeworkReportRepositoryInterface
{
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
}
