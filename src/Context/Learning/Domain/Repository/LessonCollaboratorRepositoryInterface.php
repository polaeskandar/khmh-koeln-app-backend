<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\Entity\Lesson;
use Symfony\Component\Uid\Uuid;

interface LessonCollaboratorRepositoryInterface
{
    /**
     * @param list<Uuid> $lessonIds
     *
     * @return array<string, list<string>> account ids by lesson id
     */
    public function ofLessons(array $lessonIds): array;

    /**
     * @param list<Uuid> $accountIds
     */
    public function replace(Lesson $lesson, array $accountIds): void;
}
