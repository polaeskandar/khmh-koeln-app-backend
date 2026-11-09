<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonStatus;

final readonly class WrittenLesson
{
    public function __construct(
        public string $id,
        public LessonStatus $status,
        public int $version,
    ) {
    }

    public static function of(Lesson $lesson): self
    {
        return new self($lesson->id()->toRfc4122(), $lesson->state()->status, $lesson->version());
    }
}
