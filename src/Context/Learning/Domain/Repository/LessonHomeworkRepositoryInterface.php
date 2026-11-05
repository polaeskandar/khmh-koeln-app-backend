<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use Symfony\Component\Uid\Uuid;

interface LessonHomeworkRepositoryInterface
{
    /**
     * @return list<HomeworkAssignment> the lesson's tasks, each row locked
     */
    public function lockedOf(Uuid $lessonId): array;

    public function remove(HomeworkAssignment $assignment): void;
}
