<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
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

    public function unpublish(HomeworkAssignment $task): HomeworkAssignment
    {
        $drafted = $task->state()->drafted();

        if (!$this->reports->reportedAmong([$task->id()])->isEmpty()) {
            throw new RuleViolation('homework.alreadyReported');
        }

        return $this->changed($task, $drafted);
    }

    public function archive(HomeworkAssignment $task): HomeworkAssignment
    {
        return $this->changed($task, $task->state()->archived($this->calendar->now()));
    }

    public function restore(HomeworkAssignment $task): HomeworkAssignment
    {
        $restored = $task->state()->restored();

        if ($task->lesson()->state()->archivedAt !== null) {
            throw new RuleViolation('homework.lessonArchived');
        }

        return $this->changed($task, $restored);
    }

    public function delete(HomeworkAssignment $task): HomeworkAssignment
    {
        $task->state()->ensureNeverPublished();
        $this->lessonHomework->remove($task);

        return $task;
    }

    private function changed(HomeworkAssignment $task, HomeworkState $state): HomeworkAssignment
    {
        $task->changeState($state);
        $this->homework->save($task);

        return $task;
    }
}
