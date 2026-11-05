<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use DateTimeImmutable;

final readonly class PublishedHomework
{
    public function __construct(
        public string $id,
        public string $status,
        public ?DateTimeImmutable $publishedAt,
    ) {
    }

    public static function of(HomeworkAssignment $task): self
    {
        $state = $task->state();

        return new self(id: $task->id()->toRfc4122(), status: $state->status->value, publishedAt: $state->publishedAt);
    }
}
