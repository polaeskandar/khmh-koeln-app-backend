<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;

final readonly class LessonSummary
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $passage,
        public string $teachingDate,
        public string $status,
    ) {
    }

    public static function of(Lesson $lesson): self
    {
        return new self(
            id: $lesson->id()->toRfc4122(),
            title: $lesson->text()->title,
            passage: $lesson->text()->passage,
            teachingDate: $lesson->link()->teachingDate->toString(),
            status: $lesson->state()->status->value,
        );
    }
}
