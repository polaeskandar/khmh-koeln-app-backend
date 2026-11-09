<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\Repository\LessonHomeworkRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineLessonHomeworkRepository implements LessonHomeworkRepositoryInterface
{
    private const string LOCK = <<<'SQL'
        SELECT id FROM learning.homework_assignment WHERE lesson_id = ? ORDER BY id FOR UPDATE
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<HomeworkTask>
     */
    #[Override]
    public function lockedOf(Uuid $lessonId): array
    {
        $this->entityManager->getConnection()->executeQuery(self::LOCK, [$lessonId->toRfc4122()]);

        return new ArrayCollection($this->entityManager
            ->getRepository(HomeworkTask::class)
            ->findBy(['lesson' => $lessonId]))->getValues();
    }

    #[Override]
    public function remove(HomeworkTask $task): void
    {
        $this->entityManager->remove($task);
        $this->entityManager->flush();
    }
}
