<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;

final readonly class VisibleTask
{
    public function __construct(
        public HomeworkAssignment $task,
        public ?HomeworkCompletion $report,
    ) {
    }

    public function isDone(): bool
    {
        return $this->report !== null;
    }
}
