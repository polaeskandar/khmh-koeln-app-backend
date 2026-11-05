<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Library\Collections\Model\Orderings;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ParentHomeworkOrder
{
    public const string DONE = 'done';

    private const string UNDATED = '9999-12-31';

    /**
     * @param list<VisibleTask> $tasks
     *
     * @return list<VisibleTask>
     */
    public static function shown(array $tasks, string $state): array
    {
        $done = $state === self::DONE;

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
