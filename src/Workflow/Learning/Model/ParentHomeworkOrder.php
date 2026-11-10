<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Enum\HomeworkFilter;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Library\Collections\Model\Orderings;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ParentHomeworkOrder
{
    private const string UNDATED = '9999-12-31';

    /**
     * @param list<VisibleTask> $tasks
     *
     * @return list<VisibleTask>
     */
    public static function shown(array $tasks, HomeworkFilter $filter): array
    {
        $done = $filter === HomeworkFilter::Done;

        $shown = new ArrayCollection($tasks)->filter(static fn (VisibleTask $task): bool => $task->isDone() === $done);

        if ($done) {
            return Orderings::byKeysDescending($shown, keysOf: self::doneKeys(...));
        }

        return Orderings::byKeys($shown, keysOf: self::toDoKeys(...));
    }

    /**
     * @return list<string|int>
     */
    private static function toDoKeys(VisibleTask $visible): array
    {
        $task = $visible->task;
        $publishedAt = $task->state()->publishedAt?->getTimestamp() ?? 0;

        return [$task->text()->dueDate?->toString() ?? self::UNDATED, -$publishedAt, $task->id()->toRfc4122()];
    }

    /**
     * @return list<string|int>
     */
    private static function doneKeys(VisibleTask $visible): array
    {
        return [$visible->report?->completedAt()->getTimestamp() ?? 0, $visible->task->id()->toRfc4122()];
    }
}
