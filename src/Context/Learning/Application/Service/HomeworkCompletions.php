<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\CompletionOutcome;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Library\Time\Client\Calendar;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class HomeworkCompletions
{
    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private HomeworkReportRepositoryInterface $reports,
        private Calendar $calendar,
    ) {
    }

    /**
     * @template T of object
     *
     * @param callable(HomeworkAssignment): T $work
     *
     * @return T|null null when the request names no task that exists
     */
    public function whileTaskLocked(?Uuid $homeworkId, callable $work): ?object
    {
        return $homeworkId === null ? null : $this->homework->withWriteLock($homeworkId, $work);
    }

    public function report(VisibleTask $visible, Uuid $childId, Uuid $by): CompletionOutcome
    {
        $taskId = $visible->task->id();
        $created = $this->reports->reportDone($taskId, $childId, $this->calendar->now(), $by);
        $current = $this->reports->currentReport($taskId, $childId) ?? throw new LogicException('No current report.');

        return new CompletionOutcome($created, $current->completedAt, $current->completedBy);
    }

    public function undo(VisibleTask $visible, Uuid $childId, Uuid $by): VisibleTask
    {
        $this->reports->undo($visible->task->id(), $childId, $this->calendar->now(), $by);

        return $visible;
    }
}
