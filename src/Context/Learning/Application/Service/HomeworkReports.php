<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkReading;
use App\Context\Learning\Domain\ValueObject\HomeworkReport;
use App\Context\Learning\Domain\ValueObject\StageHomework;
use App\Context\Learning\Domain\ValueObject\TaskReports;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class HomeworkReports
{
    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private HomeworkReportRepositoryInterface $completions,
        private LessonRepositoryInterface $lessons,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param list<HomeworkAssignment> $tasks
     */
    public function of(array $tasks): TaskReports
    {
        $reports = $this->completions->currentReportsOf($tasks)->map($this->reportOf(...));

        return new TaskReports($this->calendar->today(), $tasks, $reports->getValues());
    }

    public function reading(string $id): HomeworkReading
    {
        $task = Uuid::isValid($id) ? $this->homework->find(Uuid::fromString($id)) : null;
        $found = $task ?? throw NotFound::of('homework');

        return new HomeworkReading($found, $this->of([$found]));
    }

    public function ofStage(Uuid $stageId): StageHomework
    {
        return new StageHomework($this->of($this->homework->ofStage($stageId)), $this->lessons->ofStage($stageId));
    }

    /**
     * @return ReadableCollection<int, HomeworkCompletion>
     */
    public function about(Uuid $personId): ReadableCollection
    {
        return $this->completions->allAbout($personId);
    }

    /**
     * @return ReadableCollection<int, HomeworkCompletion>
     */
    public function madeBy(Uuid $accountId): ReadableCollection
    {
        return $this->completions->allBy($accountId);
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
