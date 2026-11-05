<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class LessonListing
{
    /**
     * @param array<string, int> $tabs
     * @param list<Lesson> $shown
     * @param ReadableCollection<string, HomeworkCounts> $homework
     */
    public function __construct(
        public LocalDate $today,
        public array $tabs,
        public array $shown,
        private ReadableCollection $homework,
    ) {
    }

    public function homeworkOf(Lesson $lesson): HomeworkCounts
    {
        return $this->homework->get($lesson->id()->toRfc4122()) ?? new HomeworkCounts();
    }
}
