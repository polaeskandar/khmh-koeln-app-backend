<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Library\Collections\Model\Collections;
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
     * @param list<HomeworkAssignment> $published
     * @param ReadableCollection<int, HomeworkCompletion> $reports
     */
    public static function of(Pupil $pupil, array $published, ReadableCollection $reports): self
    {
        $own = $reports->filter(static fn (HomeworkCompletion $report): bool => $report
            ->personId()
            ->equals($pupil->personId));

        $stageIds = new ArrayCollection($pupil->stageIds)->map(static fn (Uuid $id): string => $id->toRfc4122());

        $inStage = new ArrayCollection($published)->filter(
            static fn (HomeworkAssignment $task): bool => $stageIds->contains($task->stageId()->toRfc4122()),
        );

        $reported = $own->map(static fn (HomeworkCompletion $report): HomeworkAssignment => $report->assignment());

        return new self($pupil->personId, self::visible([...$inStage->getValues(), ...$reported->getValues()], $own));
    }

    public function done(): int
    {
        return new ArrayCollection($this->tasks)
            ->filter(static fn (VisibleTask $task): bool => $task->isDone())
            ->count();
    }

    public function toDo(): int
    {
        return new ArrayCollection($this->tasks)->count() - $this->done();
    }

    public function task(Uuid $homeworkId): ?VisibleTask
    {
        $found = new ArrayCollection($this->tasks)
            ->filter(static fn (VisibleTask $task): bool => $task->task->id()->equals($homeworkId))
            ->first();

        return $found === false ? null : $found;
    }

    /**
     * @param list<HomeworkAssignment> $tasks
     * @param ReadableCollection<int, HomeworkCompletion> $own
     *
     * @return list<VisibleTask>
     */
    private static function visible(array $tasks, ReadableCollection $own): array
    {
        $unique = Collections::firstPerKey(
            new ArrayCollection($tasks)->filter(
                static fn (HomeworkAssignment $task): bool => $task->isVisibleToParents(),
            ),
            keyOf: self::taskKey(...),
        );

        $reportsByTask = Collections::firstPerKey($own, keyOf: self::reportKey(...));

        return new ArrayCollection(Collections::sortedDescendingBy($unique, keyOf: self::sortKey(...)))->map(
            static fn (HomeworkAssignment $task): VisibleTask => new VisibleTask(
                $task,
                $reportsByTask->get(self::taskKey($task)),
            ),
        )->getValues();
    }

    private static function taskKey(HomeworkAssignment $task): string
    {
        return $task->id()->toRfc4122();
    }

    private static function reportKey(HomeworkCompletion $report): string
    {
        return $report->assignment()->id()->toRfc4122();
    }

    private static function sortKey(HomeworkAssignment $task): string
    {
        $date = $task->text()->dueDate ?? $task->lesson()->link()->teachingDate;

        return $date->toString() . ' ' . $task->id()->toRfc4122();
    }
}
