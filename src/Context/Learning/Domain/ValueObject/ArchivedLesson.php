<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\Lesson;

final readonly class ArchivedLesson
{
    public function __construct(
        public Lesson $lesson,
        public int $homeworkArchived,
    ) {
    }
}
