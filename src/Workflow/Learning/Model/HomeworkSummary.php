<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use DateTimeImmutable;

final readonly class HomeworkSummary
{
    public function __construct(
        public string $id,
        public string $title,
        public string $status,
        public ?string $dueDate,
        public ?DateTimeImmutable $publishedAt,
    ) {
    }

    public static function of(HomeworkAssignment $task): self
    {
        return new self(
            id: $task->id()->toRfc4122(),
            title: $task->text()->title,
            status: $task->state()->status->value,
            dueDate: $task->text()->dueDate?->toString(),
            publishedAt: $task->state()->publishedAt,
        );
    }
}
