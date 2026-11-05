<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkReport;
use App\Context\Learning\Domain\ValueObject\TaskReports;
use App\Library\Time\Client\Calendar;

final readonly class HomeworkReports
{
    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<HomeworkAssignment> $tasks
     */
    public function of(array $tasks): TaskReports
    {
        $reports = $this->homework->currentReportsOf($tasks)->map($this->reportOf(...));

        return new TaskReports($this->calendar->today(), $tasks, $reports->getValues());
    }

    private function reportOf(HomeworkCompletion $completion): HomeworkReport
    {
        $task = $completion->assignment();
        $dueDate = $task->text()->dueDate;
        $completedOn = $this->calendar->localDateOf($completion->completedAt());

        return new HomeworkReport(
            taskId: $task->id(),
            personId: $completion->personId(),
            completedAt: $completion->completedAt(),
            completedBy: $completion->completedBy(),
            late: $dueDate?->isBefore($completedOn) === true,
        );
    }
}
