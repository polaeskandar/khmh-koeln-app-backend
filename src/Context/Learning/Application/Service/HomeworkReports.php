<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkReport;
use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkReading;
use App\Context\Learning\Domain\ValueObject\StageHomework;
use App\Context\Learning\Domain\ValueObject\TaskReport;
use App\Context\Learning\Domain\ValueObject\TaskReports;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class HomeworkReports
{
    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private HomeworkReportRepositoryInterface $reports,
        private LessonRepositoryInterface $lessons,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<HomeworkTask> $tasks
     */
    public function ofTasks(array $tasks): TaskReports
    {
        $taskReports = $this->reports->currentReportsOf($tasks)->map($this->taskReportOf(...));

        return new TaskReports($this->calendar->today(), $tasks, $taskReports->getValues());
    }

    public function reading(string $id): HomeworkReading
    {
        $task = Uuid::isValid($id) ? $this->homework->find(Uuid::fromString($id)) : null;
        $found = $task ?? throw NotFound::of('homework');

        return new HomeworkReading($found, $this->ofTasks([$found]));
    }

    public function ofStage(Uuid $stageId): StageHomework
    {
        return new StageHomework($this->ofTasks($this->homework->ofStage($stageId)), $this->lessons->ofStage($stageId));
    }

    /**
     * @return ReadableCollection<int, HomeworkReport>
     */
    public function about(Uuid $personId): ReadableCollection
    {
        return $this->reports->allAbout($personId);
    }

    /**
     * @return ReadableCollection<int, HomeworkReport>
     */
    public function madeBy(Uuid $accountId): ReadableCollection
    {
        return $this->reports->allBy($accountId);
    }

    private function taskReportOf(HomeworkReport $report): TaskReport
    {
        $task = $report->task();
        $dueDate = $task->text()->dueDate;
        $completedOn = $this->calendar->localDateOf($report->completedAt());

        return new TaskReport(
            taskId: $task->id(),
            personId: $report->personId(),
            completedAt: $report->completedAt(),
            completedBy: $report->completedBy(),
            late: $dueDate?->isBefore($completedOn) === true,
        );
    }
}
