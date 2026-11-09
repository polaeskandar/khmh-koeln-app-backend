<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\HomeworkReport;
use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Library\Collections\Model\Collections;
use App\Library\Collections\Model\Orderings;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class PupilTasks
{
    /**
     * @param list<VisibleTask> $tasks
     */
    private function __construct(
        public Uuid $personId,
        public array $tasks,
    ) {
    }

    /**
     * @param list<HomeworkTask> $published
     * @param ReadableCollection<int, HomeworkReport> $reports
     */
    public static function of(Pupil $pupil, array $published, ReadableCollection $reports): self
    {
        $own = $reports->filter(static fn (HomeworkReport $report): bool => $report
            ->personId()
            ->equals($pupil->personId));

        $stageIds = new ArrayCollection($pupil->stageIds)->map(static fn (Uuid $id): string => $id->toRfc4122());

        $inStage = new ArrayCollection($published)->filter(static fn (HomeworkTask $task): bool => $stageIds->contains(
            $task->stageId()->toRfc4122(),
        ));

        $reported = $own->map(static fn (HomeworkReport $report): HomeworkTask => $report->task());

        return new self($pupil->personId, self::visible([...$inStage->getValues(), ...$reported->getValues()], $own));
    }

    public function countDone(): int
    {
        return new ArrayCollection($this->tasks)
            ->filter(static fn (VisibleTask $task): bool => $task->isDone())
            ->count();
    }

    public function countToDo(): int
    {
        return new ArrayCollection($this->tasks)->count() - $this->countDone();
    }

    public function task(Uuid $homeworkId): ?VisibleTask
    {
        $found = new ArrayCollection($this->tasks)
            ->filter(static fn (VisibleTask $task): bool => $task->task->id()->equals($homeworkId))
            ->first();

        return $found === false ? null : $found;
    }

    /**
     * @param list<HomeworkTask> $tasks
     * @param ReadableCollection<int, HomeworkReport> $own
     *
     * @return list<VisibleTask>
     */
    private static function visible(array $tasks, ReadableCollection $own): array
    {
        $unique = Collections::firstPerKey(
            new ArrayCollection($tasks)->filter(static fn (HomeworkTask $task): bool => $task->isPublished()),
            keyOf: self::taskKey(...),
        );

        $reportsByTask = Collections::firstPerKey($own, keyOf: self::reportKey(...));

        return new ArrayCollection(Orderings::byKeysDescending($unique, keysOf: self::sortKeys(...)))->map(
            static fn (HomeworkTask $task): VisibleTask => new VisibleTask(
                $task,
                $reportsByTask->get(self::taskKey($task)),
            ),
        )->getValues();
    }

    private static function taskKey(HomeworkTask $task): string
    {
        return $task->id()->toRfc4122();
    }

    private static function reportKey(HomeworkReport $report): string
    {
        return $report->task()->id()->toRfc4122();
    }

    /**
     * @return list<string>
     */
    private static function sortKeys(HomeworkTask $task): array
    {
        $date = $task->text()->dueDate ?? $task->lesson()->link()->teachingDate;

        return [$date->toString(), $task->id()->toRfc4122()];
    }
}
