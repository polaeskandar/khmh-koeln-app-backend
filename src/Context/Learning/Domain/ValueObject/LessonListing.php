<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Time\Model\LocalDate;

final readonly class LessonListing
{
    /**
     * @param array<string, int> $tabs
     * @param list<Lesson> $shown
     */
    public function __construct(
        public LocalDate $today,
        public array $tabs,
        public array $shown,
        public HomeworkTally $homework,
    ) {
    }
}
