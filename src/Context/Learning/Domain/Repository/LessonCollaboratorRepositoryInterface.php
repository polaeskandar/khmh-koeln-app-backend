<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\LessonHelpers;
use Symfony\Component\Uid\Uuid;

interface LessonCollaboratorRepositoryInterface
{
    /**
     * @param list<Uuid> $lessonIds
     */
    public function ofLessons(array $lessonIds): LessonHelpers;

    /**
     * @param list<Uuid> $accountIds
     */
    public function replace(Lesson $lesson, array $accountIds): void;
}
