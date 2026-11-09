<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\HomeworkTask;

final readonly class HomeworkReading
{
    public function __construct(
        public HomeworkTask $task,
        public TaskReports $reports,
    ) {
    }
}
