<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\TaskReports;

final readonly class HomeworkSummary
{
    public function __construct(
        public string $id,
        public string $title,
        public string $status,
        public ?string $dueDate,
        public ?string $publishedAt,
    ) {
    }

    public static function of(HomeworkAssignment $task, TaskReports $reports): self
    {
        return new self(
            id: $task->id()->toRfc4122(),
            title: $task->text()->title,
            status: $task->state()->status->value,
            dueDate: $task->text()->dueDate?->toString(),
            publishedAt: $reports->publishedAtOf($task),
        );
    }
}
