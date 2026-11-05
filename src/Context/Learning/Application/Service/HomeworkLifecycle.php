<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;

final readonly class HomeworkLifecycle
{
    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private HomeworkReportRepositoryInterface $reports,
        private Calendar $calendar,
    ) {
    }

    public function unpublish(HomeworkAssignment $task): HomeworkAssignment
    {
        if ($task->isVisibleToParents() && !$this->reports->reportedAmong([$task->id()])->isEmpty()) {
            throw new RuleViolation('homework.alreadyReported');
        }

        $task->unpublish();

        return $this->saved($task);
    }

    public function archive(HomeworkAssignment $task): HomeworkAssignment
    {
        $task->archive($this->calendar->now()->toDateTimeImmutable());

        return $this->saved($task);
    }

    public function restore(HomeworkAssignment $task): HomeworkAssignment
    {
        $task->restore();

        return $this->saved($task);
    }

    public function delete(HomeworkAssignment $task): HomeworkAssignment
    {
        if ($task->state()->publishedAt !== null) {
            throw new RuleViolation('homework.publishedStays');
        }

        $this->homework->remove($task);

        return $task;
    }

    private function saved(HomeworkAssignment $task): HomeworkAssignment
    {
        $this->homework->save($task);

        return $task;
    }
}
