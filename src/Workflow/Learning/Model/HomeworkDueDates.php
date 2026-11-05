<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Library\Time\Model\LocalDate;

final readonly class HomeworkDueDates
{
    public static function minimumOf(HomeworkAssignment $task): LocalDate
    {
        $lessonDate = $task->lesson()->link()->teachingDate;
        $dueDate = $task->text()->dueDate ?? $lessonDate;

        return $dueDate->isBefore($lessonDate) ? $dueDate : $lessonDate;
    }
}
