<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Context\Learning\Domain\Repository\LessonHomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Library\Problem\Exception\RuleViolation;
use App\Platform\Doctrine\Contract\Transactions;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class LessonDeletions
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
        private LessonHomeworkRepositoryInterface $homework,
        private Transactions $transactions,
    ) {
    }

    public function delete(Lesson $lesson): void
    {
        if ($lesson->state()->status !== LessonStatus::Draft) {
            throw new RuleViolation('lessons.deleteDraftsOnly');
        }

        $this->transactions->run(function () use ($lesson): void {
            $tasks = new ArrayCollection($this->homework->lockedOf($lesson->id()));
            $published = $tasks->filter(static fn (HomeworkTask $task): bool => $task->state()->publishedAt !== null);

            if (!$published->isEmpty()) {
                throw new RuleViolation('lessons.homeworkPublished');
            }

            foreach ($tasks as $task) {
                $this->homework->remove($task);
            }

            $this->lessons->remove($lesson);
        });
    }
}
