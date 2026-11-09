<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use DateTimeImmutable;

final readonly class ChangedHomework
{
    public function __construct(
        public string $id,
        public string $status,
        public ?DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $archivedAt,
        public int $version,
    ) {
    }

    public static function of(HomeworkTask $task): self
    {
        $state = $task->state();

        return new self(
            id: $task->id()->toRfc4122(),
            status: $state->status->value,
            publishedAt: $state->publishedAt,
            archivedAt: $state->archivedAt,
            version: $task->version(),
        );
    }
}
