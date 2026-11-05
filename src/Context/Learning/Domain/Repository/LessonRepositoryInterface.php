<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Repository;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

interface LessonRepositoryInterface
{
    public function save(Lesson $lesson): void;

    public function currentAt(Uuid $scheduleId, LocalDate $date): ?Lesson;
}
