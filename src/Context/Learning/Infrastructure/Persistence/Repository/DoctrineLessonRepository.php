<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineLessonRepository implements LessonRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Lesson $lesson): void
    {
        $this->entityManager->persist($lesson);
        $this->entityManager->flush();
    }

    #[Override]
    public function currentAt(Uuid $scheduleId, LocalDate $date): ?Lesson
    {
        return $this->entityManager
            ->getRepository(Lesson::class)
            ->findOneBy([
                'link.scheduleId' => $scheduleId,
                'link.teachingDate' => $date,
                'state.archivedAt' => null,
            ]);
    }

    #[Override]
    public function find(Uuid $id): ?Lesson
    {
        return $this->entityManager->find(Lesson::class, $id);
    }

    /**
     * @param list<Uuid> $scheduleIds
     */
    #[Override]
    public function currentBetween(array $scheduleIds, LocalDate $from, LocalDate $to): ReadableCollection
    {
        if ($scheduleIds === []) {
            return new ArrayCollection();
        }

        $query = $this->entityManager
            ->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->where('l.link.scheduleId IN (:schedules)')
            ->andWhere('l.link.teachingDate BETWEEN :from AND :to')
            ->andWhere('l.state.archivedAt IS NULL')
            ->setParameter(
                'schedules',
                new ArrayCollection($scheduleIds)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues(),
                ArrayParameterType::STRING,
            )
            ->setParameter('from', $from, 'local_date')
            ->setParameter('to', $to, 'local_date')
            ->getQuery();

        return new ArrayCollection(ListOf::instances($query->getResult(), Lesson::class));
    }
}
