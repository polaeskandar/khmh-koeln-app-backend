<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;

final readonly class ParentTaskView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $lessonTitle,
        public ?string $stage,
        public ?string $dueDate,
    ) {
    }

    public static function of(HomeworkAssignment $task, ?string $stage): self
    {
        return new self(
            id: $task->id()->toRfc4122(),
            title: $task->text()->title,
            lessonTitle: $task->lesson()->text()->title,
            stage: $stage,
            dueDate: $task->text()->dueDate?->toString(),
        );
    }
}
