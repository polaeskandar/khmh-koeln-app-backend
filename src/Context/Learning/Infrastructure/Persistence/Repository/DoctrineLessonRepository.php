<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Library\Time\Model\LocalDate;
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
}
