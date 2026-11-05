<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Time\Model\LocalDate;

final readonly class LessonReading
{
    public function __construct(
        public Lesson $lesson,
        public HomeworkCounts $homework,
        public LocalDate $today,
    ) {
    }
}
