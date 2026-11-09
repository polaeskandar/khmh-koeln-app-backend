<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\ReportOutcome;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class HomeworkReportWrites
{
    private const string MISSING_TASK = 'notFound.homework';

    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private HomeworkReportRepositoryInterface $reports,
        private Calendar $calendar,
    ) {
    }

    /**
     * @template T of object
     *
     * @param callable(HomeworkTask): T $work
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

    public function report(VisibleTask $visible, Uuid $personId, Uuid $by): ReportOutcome
    {
        $taskId = $visible->task->id();
        $created = $this->reports->reportDone($taskId, $personId, $this->calendar->now(), $by);
        $current = $this->reports->currentReport($taskId, $personId) ?? throw new LogicException('No current report.');

        return new ReportOutcome($created, $current->completedAt, $current->completedBy);
    }

    public function undo(VisibleTask $visible, Uuid $personId, Uuid $by): VisibleTask
    {
        $this->reports->undo($visible->task->id(), $personId, $this->calendar->now(), $by);

        return $visible;
    }
}
