<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Enum\HomeworkStatus;

final readonly class WrittenHomework
{
    public function __construct(
        public string $id,
        public HomeworkStatus $status,
        public int $version,
    ) {
    }

    public static function of(HomeworkAssignment $task): self
    {
        return new self($task->id()->toRfc4122(), $task->state()->status, $task->version());
    }
}
