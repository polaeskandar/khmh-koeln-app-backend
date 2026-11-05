<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\PersonTraceRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\CompletionOutcome;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class HomeworkCompletions
{
    private const string MISSING_TASK = 'notFound.homework';

    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private HomeworkReportRepositoryInterface $reports,
        private Calendar $calendar,
        private PersonTraceRepositoryInterface $traces,
    ) {
    }

    /**
     * @template T of object
     *
     * @param callable(HomeworkAssignment): T $work
     * @param callable(): never $whenMissing
     *
     * @return T
     */
    public function whileLocked(string $homeworkId, callable $work, callable $whenMissing): object
    {
        if (!Uuid::isValid($homeworkId)) {
            $whenMissing();
        }

        try {
            return $this->homework->withLock(Uuid::fromString($homeworkId), $work);
        } catch (NotFound $notFound) {
            if ($notFound->getMessage() === self::MISSING_TASK) {
                $whenMissing();
            }

            throw $notFound;
        }
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

    public function forget(Uuid $childId): int
    {
        return $this->traces->forget($childId);
    }
}
