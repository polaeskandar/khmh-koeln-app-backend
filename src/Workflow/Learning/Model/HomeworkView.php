<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\HomeworkReading;
use DateTimeImmutable;

final readonly class HomeworkView
{
    public function __construct(
        public string $id,
        public string $status,
        public ?DateTimeImmutable $publishedAt,
        public HomeworkTextView $text,
    ) {
    }

    public static function of(HomeworkReading $reading): self
    {
        $task = $reading->task;

        return new self(
            id: $task->id()->toRfc4122(),
            status: $task->state()->status->value,
            publishedAt: $task->state()->publishedAt,
            text: HomeworkTextView::of($task),
        );
    }
}
