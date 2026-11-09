<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class StageHomework
{
    /**
     * @param ReadableCollection<int, Lesson> $lessons
     */
    public function __construct(
        public TaskReports $reports,
        public ReadableCollection $lessons,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function tabs(): array
    {
        return [
            HomeworkStatus::Published->value => $this->countOf(HomeworkStatus::Published),
            HomeworkStatus::Draft->value => $this->countOf(HomeworkStatus::Draft),
            HomeworkStatus::Archived->value => $this->countOf(HomeworkStatus::Archived),
        ];
    }

    private function countOf(HomeworkStatus $status): int
    {
        return new ArrayCollection($this->reports->tasks)
            ->filter(static fn (HomeworkTask $task): bool => $task->state()->status === $status)
            ->count();
    }
}
