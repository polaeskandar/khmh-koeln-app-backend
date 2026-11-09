<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonHomeworkRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkState;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;

final readonly class HomeworkLifecycle
{
    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private LessonHomeworkRepositoryInterface $lessonHomework,
        private HomeworkReportRepositoryInterface $reports,
        private Calendar $calendar,
    ) {
    }

    public function unpublish(HomeworkTask $task): HomeworkTask
    {
        $drafted = $task->state()->drafted();

        if (!$this->reports->reportedAmong([$task->id()])->isEmpty()) {
            throw new RuleViolation('homework.alreadyReported');
        }

        return $this->changed($task, $drafted);
    }

    public function archive(HomeworkTask $task): HomeworkTask
    {
        return $this->changed($task, $task->state()->archived($this->calendar->now()));
    }

    public function restore(HomeworkTask $task): HomeworkTask
    {
        $restored = $task->state()->restored();

        if ($task->lesson()->state()->archivedAt !== null) {
            throw new RuleViolation('homework.lessonArchived');
        }

        return $this->changed($task, $restored);
    }

    public function delete(HomeworkTask $task): HomeworkTask
    {
        $task->state()->ensureNeverPublished();
        $this->lessonHomework->remove($task);

        return $task;
    }

    private function changed(HomeworkTask $task, HomeworkState $state): HomeworkTask
    {
        $task->changeState($state);
        $this->homework->save($task);

        return $task;
    }
}
