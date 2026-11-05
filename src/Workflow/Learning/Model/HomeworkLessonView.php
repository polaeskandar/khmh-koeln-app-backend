<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;

final readonly class HomeworkLessonView
{
    public function __construct(
        public string $id,
        public string $title,
        public string $teachingDate,
        public ?string $passage,
        public ?string $memoryVerse,
    ) {
    }

    public static function of(Lesson $lesson): self
    {
        $text = $lesson->text();

        return new self(
            id: $lesson->id()->toRfc4122(),
            title: $text->title,
            teachingDate: $lesson->link()->teachingDate->toString(),
            passage: $text->passage,
            memoryVerse: $text->memoryVerse,
        );
    }
}
