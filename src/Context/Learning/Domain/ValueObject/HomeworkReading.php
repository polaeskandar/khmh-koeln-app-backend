<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;

final readonly class HomeworkReading
{
    public function __construct(
        public HomeworkAssignment $task,
        public TaskReports $reports,
        public ?string $publishedAt,
    ) {
    }
}
