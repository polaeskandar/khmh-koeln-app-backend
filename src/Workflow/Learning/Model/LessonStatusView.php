<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonStatus;
use DateTimeImmutable;

final readonly class LessonStatusView
{
    public function __construct(
        public string $id,
        public LessonStatus $status,
        public ?DateTimeImmutable $completedAt,
        public int $version,
    ) {
    }

    public static function of(Lesson $lesson): self
    {
        $state = $lesson->state();

        return new self(
            id: $lesson->id()->toRfc4122(),
            status: $state->status,
            completedAt: $state->completedAt,
            version: $lesson->version(),
        );
    }
}
