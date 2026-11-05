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
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;
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

        $published = Collections::mapPresent(
            Collections::firstPerKey(new ArrayCollection($tasks), keyOf: self::taskKey(...)),
            $this->publishedAtTextOf(...),
        );

        return new TaskReports($this->calendar->today(), $tasks, $reports->getValues(), $published);
    }

    public function reading(string $id): HomeworkReading
    {
        $task = $this->homework->find(Uuid::fromString($id)) ?? throw NotFound::of('homework');

        return new HomeworkReading($task, $this->of([$task]));
    }

    public function ofStage(Uuid $stageId): StageHomework
    {
        return new StageHomework($this->of($this->homework->ofStage($stageId)), $this->lessons->ofStage($stageId));
    }

    private function reportOf(HomeworkCompletion $completion): HomeworkReport
    {
        $task = $completion->assignment();
        $dueDate = $task->text()->dueDate;
        $completedOn = $this->calendar->localDateOf($completion->completedAt());

        return new HomeworkReport(
            taskId: $task->id(),
            personId: $completion->personId(),
            completedAt: $this->calendar->formatInstant($completion->completedAt()),
            completedBy: $completion->completedBy(),
            late: $dueDate?->isBefore($completedOn) === true,
        );
    }

    private function publishedAtTextOf(HomeworkAssignment $task): ?string
    {
        $publishedAt = $task->state()->publishedAt;

        return $publishedAt === null ? null : $this->calendar->formatInstant($publishedAt);
    }

    private static function taskKey(HomeworkAssignment $task): string
    {
        return $task->id()->toRfc4122();
    }
}
