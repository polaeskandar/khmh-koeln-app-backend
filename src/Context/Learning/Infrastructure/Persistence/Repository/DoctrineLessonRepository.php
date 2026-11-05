<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\MeetingChoice;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\ListOf;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineLessonRepository implements LessonRepositoryInterface
{
    private const string COUNT_FOR_SCHEDULE = 'SELECT count(*) FROM learning.lesson WHERE schedule_id = ?';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Lesson $lesson): void
    {
        try {
            $this->entityManager->persist($lesson);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $link = $lesson->link();

            throw MeetingChoice::refusal('lessons.meetingHasLesson', [
                'title' => $this->titleAt($link->scheduleId, $link->teachingDate) ?? $lesson->text()->title,
            ]);
        }
    }

    #[Override]
    public function countForSchedule(Uuid $scheduleId): int
    {
        $count = $this->entityManager->getConnection()->fetchOne(self::COUNT_FOR_SCHEDULE, [$scheduleId->toRfc4122()]);

        return Integers::of($count);
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

    #[Override]
    public function ofStage(Uuid $stageId): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Lesson::class)->findBy(['stageId' => $stageId]));
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

    private function titleAt(?Uuid $scheduleId, LocalDate $date): ?string
    {
        $title = $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select('title')
            ->from('learning.lesson')
            ->where('schedule_id = :schedule')
            ->andWhere('teaching_date = :date')
            ->andWhere('archived_at IS NULL')
            ->setParameter('schedule', $scheduleId?->toRfc4122())
            ->setParameter('date', $date->toString())
            ->fetchOne();

        return $title === false ? null : Type::of($title)->toString();
    }
}
