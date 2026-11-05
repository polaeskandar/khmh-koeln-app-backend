<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;

final readonly class LessonTextView
{
    public function __construct(
        public ?string $summary,
        public ?string $memoryVerse,
    ) {
    }

    public static function of(Lesson $lesson): self
    {
        return new self($lesson->text()->summary, $lesson->text()->memoryVerse);
    }
}
