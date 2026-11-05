<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;

final readonly class WrittenHomework
{
    public function __construct(
        public string $id,
        public string $status,
    ) {
    }

    public static function of(HomeworkAssignment $task): self
    {
        return new self($task->id()->toRfc4122(), $task->state()->status->value);
    }
}
