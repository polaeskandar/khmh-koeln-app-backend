<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\HomeworkReport;
use App\Context\Learning\Domain\Entity\HomeworkTask;

final readonly class VisibleTask
{
    public function __construct(
        public HomeworkTask $task,
        public ?HomeworkReport $report,
    ) {
    }

    public function isDone(): bool
    {
        return $this->report !== null;
    }
}
