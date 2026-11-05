<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkReading;
use App\Context\Learning\Domain\ValueObject\HomeworkReport;
use App\Context\Learning\Domain\ValueObject\TaskReports;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

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

    public function reading(string $id): HomeworkReading
    {
        $task = Uuid::isValid($id) ? $this->homework->find(Uuid::fromString($id)) : null;
        $found = $task ?? throw NotFound::of('homework');

        return new HomeworkReading($found, $this->of([$found]));
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
