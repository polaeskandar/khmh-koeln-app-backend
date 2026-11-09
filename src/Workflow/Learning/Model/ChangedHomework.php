<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use DateTimeImmutable;

final readonly class ChangedHomework
{
    public function __construct(
        public string $id,
        public HomeworkStatus $status,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $archivedAt,
        public int $version,
    ) {
    }

    public static function of(HomeworkAssignment $task): self
    {
        $state = $task->state();

        return new self(
            id: $task->id()->toRfc4122(),
            status: $state->status,
            publishedAt: $state->publishedAt,
            archivedAt: $state->archivedAt,
            version: $task->version(),
        );
    }
}
