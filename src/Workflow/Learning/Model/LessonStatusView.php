<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;
use DateTimeImmutable;

final readonly class LessonStatusView
{
    public function __construct(
        public string $id,
        public string $status,
        public ?DateTimeImmutable $completedAt,
        public int $version,
    ) {
    }

    public static function of(Lesson $lesson): self
    {
        $state = $lesson->state();

        return new self(
            id: $lesson->id()->toRfc4122(),
            status: $state->status->value,
            completedAt: $state->completedAt,
            version: $lesson->version(),
        );
    }
}
